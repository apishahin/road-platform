<?php

namespace App\Http\Controllers;

use App\Models\AdminArea;
use App\Models\ModeratorArea;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Services\AuditService;
use Illuminate\Http\Request;

class VolunteerApplicationController extends Controller
{
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

    private function canApply(User $user): bool
    {
        return in_array($user->role, ['citizen', 'volunteer'], true) && ! $user->is_banned;
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $mine = VolunteerApplication::with('area.parent.parent.parent.parent')
            ->where('user_id', $user->id)->orderByDesc('id')->limit(5)->get();

        $pending = VolunteerApplication::with('area.parent.parent.parent.parent')
            ->where('user_id', $user->id)->where('status', 'pending')->first();

        $paths = [];
        foreach ($mine as $m) {
            $paths[$m->id] = $this->areaPath($m->area);
        }
        if ($pending) {
            $paths[$pending->id] = $this->areaPath($pending->area);
        }

        $divisions = AdminArea::where('level', 'division')->orderBy('name_en')->get()
            ->map(fn ($a) => ['id' => $a->id, 'label' => $this->label($a)])
            ->values()->all();

        return view('volunteer.apply', [
            'canApply'     => $this->canApply($user),
            'pending'      => $pending,
            'mine'         => $mine,
            'paths'        => $paths,
            'divisions'    => $divisions,
            'childrenBase' => url('/areas'),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $user = $request->user();
        abort_unless($this->canApply($user), 403);

        $data = $request->validate([
            'area_id' => ['required', 'integer', 'exists:admin_areas,id'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            'agree'   => ['accepted'],
        ], [
            'area_id.required' => __('volapp.err_area'),
            'area_id.integer'  => __('volapp.err_area'),
            'area_id.exists'   => __('volapp.err_area'),
            'message.required' => __('volapp.err_message'),
            'message.min'      => __('volapp.err_message'),
            'message.max'      => __('volapp.err_message'),
            'agree.accepted'   => __('volapp.err_agree'),
        ]);

        $areaId = (int) $data['area_id'];

        $hasPending = VolunteerApplication::where('user_id', $user->id)->where('status', 'pending')->exists();
        if ($hasPending) {
            return back()->withInput()->withErrors(['area_id' => __('volapp.err_pending')]);
        }

        $hasArea = ModeratorArea::where('user_id', $user->id)
            ->where('admin_area_id', $areaId)->whereNull('revoked_at')->exists();
        if ($hasArea) {
            return back()->withInput()->withErrors(['area_id' => __('volapp.err_has')]);
        }

        $app = VolunteerApplication::create([
            'user_id'       => $user->id,
            'admin_area_id' => $areaId,
            'message'       => trim($data['message']),
            'agreed_at'     => now(),
            'status'        => 'pending',
        ]);

        $audit->log($user, 'volunteer_application.submit', 'volunteer_application', (string) $app->id, [
            'area_id' => $areaId,
        ]);

        return redirect()->route('volunteer.apply')->with('status', __('volapp.sent_msg'));
    }
}