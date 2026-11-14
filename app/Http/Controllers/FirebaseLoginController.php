<?php

namespace App\Http\Controllers;

use App\Services\FirebaseVerifier;
use App\Services\OtpService;
use App\Models\User;
use App\Support\LoginSettings;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FirebaseLoginController extends Controller
{
    private function active(): bool
    {
        return LoginSettings::otpEnabled() && LoginSettings::provider() === 'firebase';
    }

    public function verify(Request $request, FirebaseVerifier $fb, OtpService $otp)
    {
        $fail = fn () => response()->json(['ok' => false, 'message' => __('login.bad_code')], 422);

        $phone = session('otp_phone');
        if (! $this->active() || ! $phone) {
            return $fail();
        }

        $request->validate(['id_token' => ['required', 'string', 'max:4000']]);

        $verified = $fb->phoneFor((string) $request->input('id_token'));
        $normalized = $verified ? Phone::normalize($verified) : null;
        if (! $normalized || $normalized !== $phone) {
            return $fail();
        }

        $existing = User::where('phone', $phone)->first();
        if (! $existing && ! LoginSettings::registrationOpen()) {
            session()->forget('otp_phone');

            return $fail();
        }
        $wasUnverified = $existing && ! $existing->phone_verified_at;

        $user = $otp->userFor($phone);
        if (! $user) {
            session()->forget('otp_phone');

            return $fail();
        }

        // The real owner proved the number: a password set before proof is removed.
        if ($wasUnverified && $user->password) {
            $user->forceFill(['password' => null])->save();
        }

        session()->forget('otp_phone');
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => redirect()->intended(route('home'))->getTargetUrl(),
        ]);
    }
}