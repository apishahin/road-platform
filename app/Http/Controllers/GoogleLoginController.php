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
    // Google identity parked in the session until the mobile number is given.
    private const PENDING_KEY = 'google_pending';

    /**
     * Kept for old bookmarks. Google sign-in now opens directly on /login,
     * so visitors never need to press the same Google button twice.
     */
    public function start(Request $request)
    {
        abort_unless(LoginSettings::googleEnabled(), 404);

        return redirect()->route('login');
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
            if (! LoginSettings::registrationOpen()) {
                return response()->json(['ok' => false, 'err' => 'closed'], 403);
            }

            // Keep the verified identity briefly only when a phone step is wanted.
            $request->session()->put(self::PENDING_KEY, [
                'id'    => $info['id'],
                'email' => $info['email'],
                'name'  => $info['name'],
            ]);

            // The default is genuinely one-tap: a phone number is optional and
            // can be added/verified later. An admin may make it required.
            if (LoginSettings::googlePhoneMode() === 'optional') {
                $this->complete($request, null, null);

                return response()->json(['ok' => true, 'next' => $this->intended($request)]);
            }

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

        return view('auth.google-phone', [
            'name'     => (string) ($p['name'] ?? ''),
            'optional' => LoginSettings::googlePhoneMode() === 'optional',
        ]);
    }

    /** Save the given mobile number and finish the sign-in. No OTP. */
    public function savePhone(Request $request)
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);

        if (RateLimiter::tooManyAttempts('gphone|' . $request->ip(), 6)) {
            return back()->withErrors(['phone' => __('login.wait')]);
        }
        RateLimiter::hit('gphone|' . $request->ip(), 600);

        $phone = Phone::normalize((string) $request->input('phone'));
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => __('login.bad_phone')]);
        }

        $existing = User::where('phone', $phone)->first();
        if ($existing && $existing->is_banned) {
            return back()->withInput()->withErrors(['phone' => __('login.blocked')]);
        }
        // A number already verified by another account cannot be taken over
        // without proving it - keep that protection.
        if ($existing && $existing->phone_verified_at !== null) {
            return back()->withInput()->withErrors(['phone' => __('glogin.number_in_use')]);
        }

        return $this->finish($request, $phone, $existing);
    }

    /** Skip the phone step (admin made it optional). */
    public function skip(Request $request)
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);
        abort_unless(LoginSettings::googlePhoneMode() === 'optional', 404);

        return $this->finish($request, null, null);
    }

    /** Create/link the account, then redirect after logging in. */
    private function finish(Request $request, ?string $phone, ?User $existing)
    {
        $this->complete($request, $phone, $existing);

        return redirect($this->intended($request));
    }

    /** Create/link the account and establish the authenticated session. */
    private function complete(Request $request, ?string $phone, ?User $existing): void
    {
        $p = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($p), 404);

        if ($existing) {
            $user = $existing;
            // Claiming a number that was never verified is handled the same
            // way a fresh OTP login would: it implies no separate owner.
            if ($user->phone_verified_at === null) {
                $user->password = null;
            }
            $user->email        = $user->email ?: ($p['email'] ?: null);
            $user->google_id    = $p['id'];
            $user->display_name = $user->display_name ?: ($p['name'] ?: null);
            $user->save();
        } else {
            $user = User::create([
                'phone'         => $phone,
                'email'         => $p['email'] ?: null,
                'google_id'     => $p['id'],
                'display_name'  => $p['name'] ?: null,
                'role'          => 'citizen',
                // No OTP - a Google account proves identity, not phone ownership.
                'phone_verified_at' => null,
            ]);
        }

        $request->session()->forget(self::PENDING_KEY);
        Auth::login($user, remember: true);
        $request->session()->regenerate();
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
        // Staff land on the dashboard first after any login.
        $me = $request->user();
        if ($me && $me->isModerator()) {
            return route('admin.dashboard');
        }

        $default = route('home');
        $target  = (string) $request->session()->pull('url.intended', $default);

        // Keep the open-redirect surface closed: only allow same-host URLs.
        if (! str_starts_with($target, url('/'))) {
            return $default;
        }

        return $target;
    }
}
