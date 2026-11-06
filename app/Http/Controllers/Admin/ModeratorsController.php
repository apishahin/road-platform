<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\ModeratorArea;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModeratorsController extends Controller
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

        $grants = ModeratorArea::with($with)->whereNull('revoked_at')->orderByDesc('id')->get();
        $past = ModeratorArea::with($with)->whereNotNull('revoked_at')->orderByDesc('revoked_at')->limit(20)->get();

        $paths = [];
        foreach ($grants->concat($past) as $g) {
            $paths[$g->id] = $this->areaPath($g->area);
        }

        $divisions = AdminArea::where('level', 'division')->orderBy('name_en')->get()
            ->map(fn ($a) => ['id' => $a->id, 'label' => $this->label($a)])
            ->values()->all();

        return view('admin.moderators', [
            'grants'       => $grants,
            'past'         => $past,
            'paths'        => $paths,
            'divisions'    => $divisions,
            'childrenBase' => url('/admin/areas'),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->guard();

        $data = $request->validate([
            'phone'   => ['required', 'string', 'max:30'],
            'area_id' => ['required', 'integer', 'exists:admin_areas,id'],
        ]);

        $digits = preg_replace('/\D+/', '', $data['phone']);
        if (strlen($digits) < 10) {
            return back()->withInput()->withErrors(['phone' => __('moderators.err_phone')]);
        }

        $users = User::where('phone', 'like', '%' . substr($digits, -10))->limit(2)->get();
        if ($users->count() === 0) {
            return back()->withInput()->withErrors(['phone' => __('moderators.err_nouser')]);
        }
        if ($users->count() > 1) {
            return back()->withInput()->withErrors(['phone' => __('moderators.err_many')]);
        }

        $user = $users->first();
        if ($user->is_banned) {
            return back()->withInput()->withErrors(['phone' => __('moderators.err_banned')]);
        }
        if (! in_array($user->role, ['citizen', 'volunteer'], true)) {
            return back()->withInput()->withErrors(['phone' => __('moderators.err_role')]);
        }

        $areaId = (int) $data['area_id'];
        $dup = ModeratorArea::where('user_id', $user->id)
            ->where('admin_area_id', $areaId)->whereNull('revoked_at')->exists();
        if ($dup) {
            return back()->withInput()->withErrors(['phone' => __('moderators.err_dup')]);
        }

        DB::transaction(function () use ($request, $audit, $user, $areaId) {
            $roleChanged = false;
            if ($user->role !== 'volunteer') {
                $user->role = 'volunteer';
                $user->save();
                $roleChanged = true;
            }

            $grant = ModeratorArea::create([
                'user_id'       => $user->id,
                'admin_area_id' => $areaId,
                'granted_by'    => $request->user()->id,
            ]);

            $audit->log($request->user(), 'moderator_area.grant', 'moderator_area', (string) $grant->id, [
                'user_id'      => $user->id,
                'area_id'      => $areaId,
                'role_changed' => $roleChanged,
            ]);
        });

        return redirect()->route('admin.moderators.index')->with('status', __('moderators.granted_msg'));
    }

    public function revoke(Request $request, ModeratorArea $grant, AuditService $audit)
    {
        $this->guard();

        if ($grant->revoked_at === null) {
            $grant->update([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
            ]);

            $audit->log($request->user(), 'moderator_area.revoke', 'moderator_area', (string) $grant->id, [
                'user_id' => $grant->user_id,
                'area_id' => $grant->admin_area_id,
            ]);
        }

        return back()->with('status', __('moderators.revoked_msg'));
    }
}