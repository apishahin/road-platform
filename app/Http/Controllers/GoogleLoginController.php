<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseVerifier;
use App\Support\LoginSettings;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class GoogleLoginController extends Controller
{
    // Google sign-in does not register new accounts on its own.
    private const PENDING_KEY  = 'google_pending';
    private const VERIFIED_KEY = 'google_pending_verified';

    public function start(Request $request)
    {
        abort_unless(LoginSettings::googleEnabled(), 404);

        return view('auth.google', ['fb' => LoginSettings::firebaseConfig()]);
    }

    public function verify(Request $request)
    {
        abort_unless(LoginSettings::googleEnabled(), 404);

        if (RateLimiter::tooManyAttempts('glogin|' . $request->ip(), 10)) {
            return response()->json(['ok' => false, 'err' => 'too_many'], 429);
        }
        RateLimiter::hit('glogin|' . $request->ip(), 600);

        $info = (new FirebaseVerifier())->googleInfo((string) $request->input('id_token'));
        if (! $info) {
            return response()->json(['ok' => false, 'err' => 'invalid'], 422);
        }

        $user = $this->findGoogleUser($info);

        if (! $user) {
            // No usable account: park the verified Google identity in the
            // session and ask for the user's mobile number next.
            if (! LoginSettings::registrationOpen()) {
                return response()->json(['ok' => false, 'err' => 'closed'], 403);
            }
            $request->session()->put(self::PENDING_KEY, [
                'id'    => $info['id'],
                'email' => $info['email'],
                'name'  => $info['name'],
            ]);

            return response()->json(['ok' => true, 'next' => route('login.google.phone')]);
        }

        if ($user->is_banned) {
            return response()->json(['ok' => false, 'err' => 'banned'], 403);
        }

        $this->stamp($user, $info);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'next' => $this->intended($request)]);
    }

    /** Show the "what is your mobile number?" step after sign-in. */
    public function phone(Request $request)
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);
        abort_unless(LoginSettings::registrationOpen(), 404);

        $s = LoginSettings::all();

        return view('auth.google-phone', [
            'name'    => (string) ($p['name'] ?? ''),
            'provider' => (string) $s['otp_provider'],
            'fb'      => LoginSettings::firebaseConfig(),
            'sent'    => $request->session()->has('google_phone_sent'),
            'needsOtp' => ! $request->session()->has(self::VERIFIED_KEY),
        ]);
    }

    /** Send the OTP for the number the user gave (not used for firebase provider). */
    public function phoneSend(Request $request)
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);
        abort_unless((string) LoginSettings::all()['otp_provider'] !== 'firebase', 404);

        if (RateLimiter::tooManyAttempts('gphone|' . $request->ip(), 6)) {
            return back()->withErrors(['phone' => __('login.wait')]);
        }

        $phone = Phone::normalize((string) $request->input('phone'));
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => __('login.bad_phone')]);
        }

        $existing = User::where('phone', $phone)->first();
        if ($existing && $existing->is_banned) {
            return back()->withInput()->withErrors(['phone' => __('login.blocked')]);
        }

        RateLimiter::hit('gphone|' . $request->ip(), 600);

        // Reuse the ordinary OTP flow: park the phone, then let OtpService send.
        $status = app(\App\Services\OtpService::class)->send($phone, (string) $request->ip());
        if ($status !== 'sent') {
            $key = ['wait' => 'login.wait', 'blocked' => 'login.blocked', 'failed' => 'smsgw.send_failed'][$status] ?? 'login.wait';

            return back()->withInput()->withErrors(['phone' => __($key)]);
        }
        $request->session()->put('google_phone', $phone);
        $request->session()->put('google_phone_sent', true);

        return back()->with('gcode_ok', true);
    }

    /** Check the OTP for the parked phone (non-firebase providers). */
    public function phoneVerify(Request $request)
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);
        abort_unless((string) LoginSettings::all()['otp_provider'] !== 'firebase', 404);

        $phone = (string) $request->session()->get('google_phone', '');
        $code  = trim((string) $request->input('code'));

        if ($phone === '' || $code === '' ||
            app(\App\Services\OtpService::class)->verify($phone, $code) !== 'ok') {
            return back()->withErrors(['code' => __('login.bad_code')]);
        }

        $request->session()->put(self::VERIFIED_KEY, $phone);

        return $this->finish($request, $phone);
    }

    /** Verify the Firebase phone-OTP token for the parked flow. */
    public function phoneFirebaseVerify(Request $request)
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);
        abort_unless((string) LoginSettings::all()['otp_provider'] === 'firebase', 404);

        if (RateLimiter::tooManyAttempts('gphone|' . $request->ip(), 6)) {
            return response()->json(['ok' => false, 'err' => 'too_many'], 429);
        }
        RateLimiter::hit('gphone|' . $request->ip(), 600);

        $phone = (new FirebaseVerifier())->phoneFor((string) $request->input('id_token'));
        if (! $phone) {
            return response()->json(['ok' => false, 'err' => 'invalid'], 422);
        }

        $existing = User::where('phone', $phone)->first();
        if ($existing && $existing->is_banned) {
            return response()->json(['ok' => false, 'err' => 'banned'], 403);
        }

        return $this->finish($request, $phone, json: true);
    }

    /** Create/link the account after the mobile number is proven, then log in. */
    private function finish(Request $request, string $phone, bool $json = false)
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);

        $user = User::where('phone', $phone)->first();

        if ($user) {
            if ($user->is_banned) {
                abort(403);
            }
            // Claiming a number that was never verified is handled the same
            // way a fresh OTP login would: it implies no separate owner.
            if ($user->phone_verified_at === null) {
                $user->password = null;
            }
            $user->email     = $user->email ?: ($p['email'] ?: null);
            $user->google_id = $p['id'];
            $user->display_name = $user->display_name ?: ($p['name'] ?: null);
            $user->phone_verified_at = now();
            $user->save();
        } else {
            $user = User::create([
                'phone' => $phone,
                'email' => $p['email'] ?: null,
                'google_id' => $p['id'],
                'display_name' => $p['name'] ?: null,
                'phone_verified_at' => now(),
            ]);
        }

        $request->session()->forget([self::PENDING_KEY, self::VERIFIED_KEY, 'google_phone', 'google_phone_sent']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $next = $this->intended($request);
        if ($json) {
            return response()->json(['ok' => true, 'next' => $next]);
        }

        return redirect($next);
    }

    private function findGoogleUser(array $info): ?User
    {
        // 1. by Google id
        $user = User::where('google_id', $info['id'])->first();
        if ($user) {
            return $user;
        }

        // 2. by email
        if ($info['email'] !== '') {
            $user = User::where('email', $info['email'])->first();
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function stamp(User $user, array $info): void
    {
        $dirty = false;
        if ($user->google_id !== $info['id']) {
            $user->google_id = $info['id'];
            $dirty = true;
        }
        if ($info['email'] !== '' && $user->email === null) {
            $user->email = $info['email'];
            $dirty = true;
        }
        if ($info['name'] !== '' && ($user->display_name === null || $user->display_name === '')) {
            $user->display_name = $info['name'];
            $dirty = true;
        }
        if ($dirty) {
            $user->save();
        }
    }

    private function intended(Request $request): string
    {
        $default = route('home');
        $target  = (string) $request->session()->pull('url.intended', $default);

        // Keep the open-redirect surface closed: only allow same-host URLs.
        if (! str_starts_with($target, url('/'))) {
            return $default;
        }

        return $target;
    }
}
