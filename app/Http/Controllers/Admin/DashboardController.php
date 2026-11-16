<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use App\Models\VolunteerApplication;
use Illuminate\Http\Request;

/** Admin landing: one glance at everything that needs attention. */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'admin', 403);

        return view('admin.dashboard', [
            'stats' => [
                'reports_pending'      => Report::where('moderation', 'pending')->count(),
                'applications_pending' => VolunteerApplication::where('status', 'pending')->count(),
                'users_total'          => User::count(),
                'users_unverified'     => User::whereNotNull('phone')->whereNull('phone_verified_at')->count(),
                'users_nophone'        => User::whereNull('phone')->count(),
                'blocked_ips'          => BlockedIp::count(),
                'issues_week'          => Issue::where('created_at', '>=', now()->subWeek())->count(),
            ],
        ]);
    }
}
