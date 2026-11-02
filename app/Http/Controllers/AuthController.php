<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp)
    {
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function sendCode(Request $request)
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $phone = Phone::normalize($request->input('phone'));
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => __('login.bad_phone')]);
        }

        return $this->dispatchCode($phone, $request);
    }

    public function resend(Request $request)
    {
        $phone = session('otp_phone');
        if (! $phone) {
            return redirect()->route('login');
        }

        return $this->dispatchCode($phone, $request);
    }

    private function dispatchCode(string $phone, Request $request)
    {
        $result = $this->otp->send($phone, (string) $request->ip());

        if ($result === 'blocked') {
            return back()->withInput()->withErrors(['phone' => __('login.blocked')]);
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

        return view('auth.verify', ['masked' => Phone::mask($phone)]);
    }

    public function verify(Request $request)
    {
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

        $user = $this->otp->userFor($phone);
        if (! $user) {
            session()->forget('otp_phone');

            return redirect()->route('login')->withErrors(['phone' => __('login.blocked')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', __('login.welcome'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}