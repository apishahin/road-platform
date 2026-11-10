<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AccountPasswordController extends Controller
{
    public function show(Request $request)
    {
        return view('account.password', ['hasPassword' => !empty($request->user()->password)]);
    }

    public function save(Request $request)
    {
        $user = $request->user();
        $key = 'pwset:' . $user->id;
        $back = redirect()->route('account.password');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $back->withErrors(['pw' => __('acctpw.wait')]);
        }

        if (!empty($user->password)) {
            $cur = (string) $request->input('current_password', '');
            if ($cur === '' || !Hash::check($cur, $user->password)) {
                RateLimiter::hit($key, 600);
                return $back->withErrors(['pw' => __('acctpw.bad_current')]);
            }
        }

        $p = (string) $request->input('password', '');
        $c = (string) $request->input('password_confirmation', '');
        $len = mb_strlen($p);
        if ($len < 8 || $len > 100) {
            return $back->withErrors(['pw' => __('loginpw.pw_short')]);
        }
        if ($p !== $c) {
            return $back->withErrors(['pw' => __('loginpw.pw_mismatch')]);
        }

        $had = !empty($user->password); $user->forceFill(['password' => $p])->save(); app(\App\Services\AuditService::class)->log($user, 'user.password_set', 'user', (string) $user->id, ['had_password' => $had]);
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return $back->with('pw_ok', __('acctpw.saved'));
    }
}