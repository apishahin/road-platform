<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OtpService;
use App\Support\LoginSettings;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp)
    {
    }

    public function showLogin()
    {
        return view('auth.login', [
            's'  => LoginSettings::all(),
            'fb' => LoginSettings::firebaseConfig(),
        ]);
    }

    public function sendCode(Request $request)
    {
        if (! LoginSettings::otpEnabled()) {
            return back()->withErrors(['phone' => __('loginpw.otp_off')]);
        }

        $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $phone = Phone::normalize($request->input('phone'));
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => __('login.bad_phone')]);
        }

        return $this->dispatchCode($phone, $request);
    }

    public function resend(Request $request)
    {
        if (! LoginSettings::otpEnabled()) {
            return redirect()->route('login')->withErrors(['phone' => __('loginpw.otp_off')]);
        }

        $phone = session('otp_phone');
        if (! $phone) {
            return redirect()->route('login');
        }

        return $this->dispatchCode($phone, $request);
    }

    private function dispatchCode(string $phone, Request $request)
    {
        if (! LoginSettings::registrationOpen() && ! User::where('phone', $phone)->exists()) {
            return redirect()->route('login')->withErrors(['phone' => __('loginpw.reg_closed')]);
        }

        if (LoginSettings::provider() === 'firebase') {
            // Firebase sends the SMS from the browser; the server only remembers the number.
            session(['otp_phone' => $phone]);

            return redirect()->route('login.verify');
        }

        $result = $this->otp->send($phone, (string) $request->ip());

        if ($result === 'blocked') {
            return back()->withInput()->withErrors(['phone' => __('login.blocked')]);
        }
        if ($result === 'failed') {
            return back()->withInput()->withErrors(['phone' => __('smsgw.send_failed')]);
        }

        if ($result === 'wait') {
            session(['otp_phone' => $phone]);

            return redirect()->route('login.verify')->withErrors(['code' => __('login.wait')]);
        }

        session(['otp_phone' => $phone]);

        return redirect()->route('login.verify')->with('status', __('login.sent'));
    }

    public function showVerify()
    {
        $phone = session('otp_phone');
        if (! $phone) {
            return redirect()->route('login');
        }

        if (LoginSettings::provider() === 'firebase') {
            $cfg = LoginSettings::all();

            return view('auth.verify-firebase', [
                'masked' => Phone::mask($phone),
                'phone' => $phone,
                'fb' => [
                    'apiKey' => (string) $cfg['firebase_api_key'],
                    'authDomain' => (string) $cfg['firebase_auth_domain'],
                    'projectId' => (string) $cfg['firebase_project_id'],
                ],
            ]);
        }

        return view('auth.verify', ['masked' => Phone::mask($phone)]);
    }

    public function verify(Request $request)
    {
        if (! LoginSettings::otpEnabled()) {
            session()->forget('otp_phone');

            return redirect()->route('login')->withErrors(['phone' => __('loginpw.otp_off')]);
        }

        $phone = session('otp_phone');
        if (! $phone) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $code = preg_replace('/\D/', '', Phone::digits($request->input('code')));
        if (strlen($code) !== 6) {
            return back()->withErrors(['code' => __('login.bad_code')]);
        }

        $result = $this->otp->verify($phone, $code);

        if ($result === 'locked') {
            return back()->withErrors(['code' => __('login.locked')]);
        }
        if ($result !== 'ok') {
            return back()->withErrors(['code' => __('login.bad_code')]);
        }

        $existing = User::where('phone', $phone)->first();
        if (! $existing && ! LoginSettings::registrationOpen()) {
            session()->forget('otp_phone');

            return redirect()->route('login')->withErrors(['phone' => __('loginpw.reg_closed')]);
        }
        $wasUnverified = $existing && ! $existing->phone_verified_at;

        $user = $this->otp->userFor($phone);
        if (! $user) {
            session()->forget('otp_phone');

            return redirect()->route('login')->withErrors(['phone' => __('login.blocked')]);
        }

        // The real owner proved the number: a password set before proof is removed.
        if ($wasUnverified && $user->password) {
            $user->forceFill(['password' => null])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Staff land on the dashboard first; citizens go back to wherever they were.
        if ($user->isModerator()) {
            return redirect()->route('admin.dashboard')->with('status', __('login.welcome'));
        }

        return redirect()->intended(route('home'))->with('status', __('login.welcome'));
    }

    public function passwordLogin(Request $request)
    {
        if (! LoginSettings::passwordEnabled()) {
            return back()->withErrors(['pwlogin' => __('loginpw.pw_off')]);
        }

        $keep = $request->only('phone');
        $phone = Phone::normalize((string) $request->input('phone'));
        $password = (string) $request->input('password');
        if (! $phone || $password === '') {
            return back()->withInput($keep)->withErrors(['pwlogin' => __('loginpw.bad_login')]);
        }

        $pk = 'pw-phone:' . sha1($phone);
        $ik = 'pw-ip:' . sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($pk, 5) || RateLimiter::tooManyAttempts($ik, 30)) {
            return back()->withInput($keep)->withErrors(['pwlogin' => __('loginpw.wait')]);
        }

        $user = User::where('phone', $phone)->first();
        $ok = false;
        if ($user && $user->password) {
            $ok = Hash::check($password, $user->password);
        } else {
            Hash::make($password); // keeps the time about the same
        }
        if ($ok && $user->is_banned) {
            $ok = false;
        }
        if (! $ok) {
            RateLimiter::hit($pk, 600);
            RateLimiter::hit($ik, 3600);

            return back()->withInput($keep)->withErrors(['pwlogin' => __('loginpw.bad_login')]);
        }

        if ($user->role !== 'citizen' && ! LoginSettings::passwordForStaff()) {
            return back()->withInput($keep)->withErrors(['pwlogin' => __('loginpw.staff_off')]);
        }

        RateLimiter::clear($pk);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Staff land on the dashboard first; citizens go back to wherever they were.
        if ($user->isModerator()) {
            return redirect()->route('admin.dashboard')->with('status', __('login.welcome'));
        }

        return redirect()->intended(route('home'))->with('status', __('login.welcome'));
    }

    public function register(Request $request)
    {
        if (! LoginSettings::passwordEnabled()) {
            return back()->withErrors(['register' => __('loginpw.pw_off')]);
        }
        if (! LoginSettings::registrationOpen()) {
            return back()->withErrors(['register' => __('loginpw.reg_closed')]);
        }

        $keep = $request->only('phone');
        $phone = Phone::normalize((string) $request->input('phone'));
        if (! $phone) {
            return back()->withInput($keep)->withErrors(['register' => __('login.bad_phone')]);
        }

        $pw = (string) $request->input('password');
        $len = mb_strlen($pw);
        if ($len < 8 || $len > 100) {
            return back()->withInput($keep)->withErrors(['register' => __('loginpw.pw_short')]);
        }
        if ($pw !== (string) $request->input('password_confirmation')) {
            return back()->withInput($keep)->withErrors(['register' => __('loginpw.pw_mismatch')]);
        }

        $ik = 'reg-ip:' . sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($ik, 10)) {
            return back()->withInput($keep)->withErrors(['register' => __('loginpw.wait')]);
        }
        RateLimiter::hit($ik, 3600);

        if (User::where('phone', $phone)->exists()) {
            return back()->withInput($keep)->withErrors(['register' => __('loginpw.phone_taken')]);
        }

        $user = new User(['phone' => $phone, 'display_name' => 'User', 'role' => 'citizen']);
        $user->forceFill(['password' => $pw])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', __('loginpw.registered'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}