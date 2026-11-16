<?php

namespace App\Http\Middleware;

use App\Support\LoginSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional admin gate (Login Settings): when `require_verified_phone` is on,
 * citizens need a verified mobile number before they can report, confirm or
 * observe issues, or apply as a volunteer. They are sent to /account/phone
 * and afterwards return to the page they wanted.
 *
 * The middleware is attached only to the gated routes, so the verification
 * pages themselves can never loop.
 */
class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! LoginSettings::requireVerifiedPhone()) {
            return $next($request);
        }

        $u = $request->user();
        if ($u && $u->phone !== null && $u->phone_verified_at !== null) {
            return $next($request);
        }

        // Remember where they wanted to go so verification can send them back.
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        } else {
            $ref = (string) $request->headers->get('referer');
            if ($ref !== '' && str_starts_with($ref, url('/'))) {
                $request->session()->put('url.intended', $ref);
            }
        }

        return redirect()->route('account.phone')->with('status', __('acctphone.required_notice'));
    }
}
