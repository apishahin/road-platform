<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site-wide gate: any IP in the `blocked_ips` table gets a plain 403,
 * whatever the page. Admins put IPs here when blocking an abusive user;
 * those users' live sessions are also killed at the same time.
 */
class BlockBannedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (BlockedIp::where('ip', (string) $request->ip())->exists()) {
            abort(403, __('adminusers.ip_blocked_msg'));
        }

        return $next($request);
    }
}
