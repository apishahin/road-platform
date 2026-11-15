<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseVerifier;
use App\Services\OtpService;
use App\Support\LoginSettings;
use App\Support\Phone;
use Illuminate\Http\Request;

/**
 * Lets a logged-in user verify (or first set and verify) the mobile number
 * on their account. Used mainly by accounts created through Google sign-in,
 * where the number is saved without an OTP, or skipped entirely.
 */
class PhoneVerificationController extends Controller
{
    private const SESSION_PHONE = 'acct_phone';
    private const SESSION_SENT  = 'acct_phone_sent';

    public function show(Request $request)
    {
        $user = $request->user();

        $target = (string) $request->session()->get(self::SESSION_PHONE, '');
        if ($target === '') {
            $target = (string) ($user->phone ?? '');
        }

        return view('account.phone', [
            'user'     => $user,
            'target'   => $target,
            'masked'   => $target !== '' ? Phone::mask($target) : '',
            'verified' => $user->phone !== null && $user->phone_verified_at !== null,
            'noPhone'  => $user->phone === null,
            'sent'     => $request->session()->has(self::SESSION_SENT),
            'provider' => LoginSettings::provider(),
            'fb'       => LoginSettings::firebaseConfig(),
        ]);
    }

    /** Send (or resend) the verification code for the target number. */
    public function send(Request $request, OtpService $otp)
    {
        $user = $request->user();

        // Already verified: nothing to do.
        if ($user->phone !== null && $user->phone_verified_at !== null) {
            return redirect()->route('account.phone');
        }

        $target = (string) ($user->phone ?? '');

        // Without a saved number (or while the saved one is still unverified)
        // the user types the number themselves.
        if ($request->filled('phone')) {
            $phone = Phone::normalize((string) $request->input('phone'));
            if (! $phone) {
                return back()->withInput()->withErrors(['phone' => __('login.bad_phone')]);
            }
            $owner = User::where('phone', $phone)->first();
            if ($owner && $owner->id !== $user->id) {
                return back()->withInput()->withErrors(['phone' => __('glogin.number_in_use')]);
            }
            $target = $phone;
        }

        if ($target === '') {
            return back()->withErrors(['phone' => __('login.bad_phone')]);
        }

        if (LoginSettings::provider() === 'firebase') {
            // Firebase sends the SMS from the browser; remember the number only.
            $request->session()->put(self::SESSION_PHONE, $target);
            $request->session()->put(self::SESSION_SENT, true);

            return redirect()->route('account.phone');
        }

        $result = $otp->send($target, (string) $request->ip());
        if ($result === 'blocked') {
            return back()->withErrors(['phone' => __('login.blocked')]);
        }
        if ($result === 'failed') {
            return back()->withErrors(['phone' => __('smsgw.send_failed')]);
        }
        if ($result === 'wait') {
            return back()->withErrors(['phone' => __('login.wait')]);
        }

        $request->session()->put(self::SESSION_PHONE, $target);
        $request->session()->put(self::SESSION_SENT, true);

        return redirect()->route('account.phone')->with('status', __('login.sent'));
    }

    /** Check the code (log/gateway providers). */
    public function verify(Request $request, OtpService $otp)
    {
        $user = $request->user();
        $target = $this->target($request);
        abort_unless($target !== '', 404);

        $code = trim((string) $request->input('code'));
        if ($code === '' || $otp->verify($target, $code) !== 'ok') {
            return back()->withErrors(['code' => __('login.bad_code')]);
        }

        $this->finalize($user, $target);
        $request->session()->forget([self::SESSION_PHONE, self::SESSION_SENT]);

        return redirect()->route('account.phone')->with('status', __('acctphone.verified_msg'));
    }

    /** Check the Firebase phone-OTP token (firebase provider). */
    public function firebase(Request $request)
    {
        abort_unless(LoginSettings::provider() === 'firebase', 404);

        $user = $request->user();
        $target = $this->target($request);
        abort_unless($target !== '', 404);

        $phone = (new FirebaseVerifier())->phoneFor((string) $request->input('id_token'));
        if (! $phone || $phone !== $target) {
            return response()->json(['ok' => false, 'err' => 'invalid'], 422);
        }

        $this->finalize($user, $target);
        $request->session()->forget([self::SESSION_PHONE, self::SESSION_SENT]);

        return response()->json(['ok' => true, 'redirect' => route('account.phone')]);
    }

    /** Hide the "verify your number" banner for the session. */
    public function dismiss(Request $request)
    {
        $request->session()->put('acct_phone_banner_dismissed', true);

        return back();
    }

    private function target(Request $request): string
    {
        $t = (string) $request->session()->get(self::SESSION_PHONE, '');

        return $t !== '' ? $t : (string) ($request->user()->phone ?? '');
    }

    private function finalize(User $user, string $phone): void
    {
        $user->phone = $phone;
        $user->phone_verified_at = now();
        $user->save();
    }
}
