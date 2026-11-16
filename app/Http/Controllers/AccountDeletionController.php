<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service account deletion for citizens, confirmed by an OTP sent to
 * their own number. Instead of hard-deleting the row (public reports and
 * uploaded evidence have foreign keys and stay for the public record),
 * every personal identifier is removed: phone, name, email, Google link,
 * password and remember-token. The account can never be logged into again,
 * and the same number can register fresh later.
 */
class AccountDeletionController extends Controller
{
    private const SESSION_SENT = 'acct_delete_sent';

    public function show(Request $request)
    {
        $user = $request->user();

        return view('account.delete', [
            'isStaff' => $user->role !== 'citizen',
            'noPhone' => $user->phone === null,
            'masked'  => $user->phone ? Phone::mask($user->phone) : '',
            'sent'    => $request->session()->has(self::SESSION_SENT),
        ]);
    }

    /** Step 1: send the confirmation code to the account's own number. */
    public function send(Request $request, OtpService $otp)
    {
        $user = $request->user();
        abort_unless($user->role === 'citizen', 403);
        abort_unless($user->phone !== null, 404);

        $result = $otp->send($user->phone, (string) $request->ip());
        if ($result === 'blocked') {
            return back()->withErrors(['phone' => __('login.blocked')]);
        }
        if ($result === 'failed') {
            return back()->withErrors(['phone' => __('smsgw.send_failed')]);
        }
        if ($result === 'wait') {
            return back()->withErrors(['phone' => __('login.wait')]);
        }

        $request->session()->put(self::SESSION_SENT, true);

        return redirect()->route('account.delete')->with('status', __('login.sent'));
    }

    /** Step 2: correct code → anonymise and log out. */
    public function destroy(Request $request, OtpService $otp)
    {
        $user = $request->user();
        abort_unless($user->role === 'citizen', 403);
        abort_unless($user->phone !== null, 404);

        $code = trim((string) $request->input('code'));
        if ($code === '' || $otp->verify($user->phone, $code) !== 'ok') {
            return back()->withErrors(['code' => __('login.bad_code')]);
        }

        app(AuditService::class)->log($user, 'account.delete', 'user', (string) $user->id);

        $user->forceFill([
            'phone'             => null,
            'phone_verified_at' => null,
            'display_name'      => null,
            'email'             => null,
            'google_id'         => null,
            'password'          => null,
            'remember_token'    => null,
        ])->save();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('acctphone.deleted_msg'));
    }
}
