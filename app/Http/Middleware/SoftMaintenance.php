<?php

namespace App\Http\Middleware;

use App\Support\SystemState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft maintenance mode: while ON, visitors see a friendly 503 page, but
 * admins and the login flow keep working so the site can always be managed
 * (unlike `artisan down`, which can lock the operator out).
 */
class SoftMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->allowed($request)) {
            return $next($request);
        }

        $m = SystemState::maintenance();
        if (! $m['on']) {
            return $next($request);
        }

        return response()->view('maintenance', ['msg' => $m['message']], 503);
    }

    private function allowed(Request $request): bool
    {
        if ($request->user()?->role === 'admin') {
            return true;
        }

        // Login (incl. Firebase/Google callbacks) and the admin area stay
        // reachable; admin routes enforce their own auth/role guards.
        return $request->is('login', 'login/*', 'admin', 'admin/*', 'up');
    }
}
