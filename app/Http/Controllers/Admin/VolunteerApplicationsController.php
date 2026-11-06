<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\ModeratorArea;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VolunteerApplicationsController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    private function label(AdminArea $a): string
    {
        $isBn = app()->getLocale() === 'bn';

        return $isBn ? ($a->name_bn ?: (string) $a->name_en) : ($a->name_en ?: (string) $a->name_bn);
    }

    private function areaPath(?AdminArea $a): string
    {
        $parts = [];
        while ($a) {
            $parts[] = $this->label($a);
            $a = $a->parent;
        }

        return implode(' > ', array_reverse($parts));
    }

    public function index()
    {
        $this->guard();

        $with = ['user', 'area.parent.parent.parent.parent'];

        $pending = VolunteerApplication::with($with)->where('status', 'pending')->orderBy('id')->get();
        $decided = VolunteerApplication::with($with)->where('status', '!=', 'pending')
            ->orderByDesc('decided_at')->limit(20)->get();

        $paths = [];
        foreach ($pending->concat($decided) as $a) {
            $paths[$a->id] = $this->areaPath($a->area);
        }

        return view('admin.applications', [
            'pending' => $pending,
            'decided' => $decided,
            'paths'   => $paths,
        ]);
    }

    public function approve(Request $request, VolunteerApplication $application, AuditService $audit)
    {
        $this->guard();

        $result = DB::transaction(function () use ($request, $audit, $application) {
            $app = VolunteerApplication::whereKey($application->id)->lockForUpdate()->first();
            if (! $app || $app->status !== 'pending') {
                return 'err_done';
            }

            $user = User::whereKey($app->user_id)->lockForUpdate()->first();
            if (! $user || $user->is_banned) {
                return 'err_banned';
            }
            if (! in_array($user->role, ['citizen', 'volunteer'], true)) {
                return 'err_role';
            }

            $roleChanged = false;
            if ($user->role !== 'volunteer') {
                $user->role = 'volunteer';
                $user->save();
                $roleChanged = true;
            }

            $grantId = null;
            $dup = ModeratorArea::where('user_id', $user->id)
                ->where('admin_area_id', $app->admin_area_id)->whereNull('revoked_at')->exists();
            if (! $dup) {
                $grant = ModeratorArea::create([
                    'user_id'       => $user->id,
                    'admin_area_id' => $app->admin_area_id,
                    'granted_by'    => $request->user()->id,
                ]);
                $grantId = $grant->id;

                $audit->log($request->user(), 'moderator_area.grant', 'moderator_area', (string) $grant->id, [
                    'user_id'        => $user->id,
                    'area_id'        => $app->admin_area_id,
                    'role_changed'   => $roleChanged,
                    'application_id' => $app->id,
                ]);
            }

            $app->update([
                'status'     => 'approved',
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            $audit->log($request->user(), 'volunteer_application.approve', 'volunteer_application', (string) $app->id, [
                'user_id'  => $user->id,
                'area_id'  => $app->admin_area_id,
                'grant_id' => $grantId,
            ]);

            return 'ok';
        });

        if ($result !== 'ok') {
            return back()->withErrors(['application' => __('volapp.' . $result)]);
        }

        return back()->with('status', __('volapp.approved_msg'));
    }

    public function reject(Request $request, VolunteerApplication $application, AuditService $audit)
    {
        $this->guard();

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => __('volapp.err_reason'),
            'reason.max'      => __('volapp.err_reason'),
        ]);

        $result = DB::transaction(function () use ($request, $audit, $application, $data) {
            $app = VolunteerApplication::whereKey($application->id)->lockForUpdate()->first();
            if (! $app || $app->status !== 'pending') {
                return 'err_done';
            }

            $app->update([
                'status'        => 'rejected',
                'decision_note' => trim($data['reason']),
                'decided_by'    => $request->user()->id,
                'decided_at'    => now(),
            ]);

            $audit->log($request->user(), 'volunteer_application.reject', 'volunteer_application', (string) $app->id, [
                'user_id' => $app->user_id,
                'area_id' => $app->admin_area_id,
            ]);

            return 'ok';
        });

        if ($result !== 'ok') {
            return back()->withErrors(['application' => __('volapp.' . $result)]);
        }

        return back()->with('status', __('volapp.rejected_msg'));
    }
}