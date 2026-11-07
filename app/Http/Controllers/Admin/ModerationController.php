<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ModerationController extends Controller
{
    public function __construct(private ModerationService $moderation)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeModerator($request);

        $reports = Report::with(['issue.category', 'evidence'])
            ->where('moderation', 'pending')
            ->tap(fn ($q) => \App\Support\ModerationScope::restrict($q, $request->user()))
            ->orderBy('id')
            ->limit(20)
            ->get();

        return view('admin.moderation', [
            'reports'      => $reports,
            'pendingTotal' => Report::where('moderation', 'pending')->tap(fn ($q) => \App\Support\ModerationScope::restrict($q, $request->user()))->count(),
        ]);
    }

    public function approve(Request $request, Report $report)
    {
        $this->authorizeModerator($request);
        $data = $request->validate([
            'area_id' => ['nullable', 'integer', 'exists:admin_areas,id'],
        ]);
        $areaId = isset($data['area_id']) ? (int) $data['area_id'] : null;
        \App\Support\ModerationScope::authorizeReport($request->user(), $report);
        \App\Support\ModerationScope::authorizeArea($request->user(), $areaId);
        $issue = $report->issue;
        $hadArea = $issue?->admin_area_id !== null;

        $this->moderation->approve($report, $request->user(), $areaId);

        if ($issue && $areaId && ! $hadArea) {
            $issue->refresh();
            if ((int) $issue->admin_area_id === $areaId) {
                app(\App\Services\AuditService::class)->log($request->user(), 'issue.area_set', 'issue', (string) $issue->id, [
                    'area_id' => $areaId,
                    'report_id' => $report->id,
                ]);
            }
        }

        return back()->with('status', __('report.mod_approved_msg'));
    }

    public function hide(Request $request, Report $report)
    {
        $this->authorizeModerator($request);
        \App\Support\ModerationScope::authorizeReport($request->user(), $report);
        $this->moderation->hide($report, $request->user());

        return back()->with('status', __('report.mod_hidden_msg'));
    }

    public function hideEvidence(Request $request, Evidence $evidence)
    {
        $this->authorizeModerator($request);
        abort_unless($evidence->report, 404);
        \App\Support\ModerationScope::authorizeReport($request->user(), $evidence->report);

        $data = $request->validate([
            'reason' => ['required', 'in:face,plate,personal,other'],
        ]);

        $done = $this->moderation->hideEvidence($evidence, $request->user(), $data['reason']);

        if ($done) {
            app(\App\Services\AuditService::class)->log($request->user(), 'evidence.hide', 'evidence', (string) $evidence->id, [
                'report_id' => $evidence->report_id,
                'reason'    => $data['reason'],
            ]);
        }

        return back()->with('status', __('evmod.hidden_msg'));
    }

    public function blurEvidence(Request $request, Evidence $evidence)
    {
        $this->authorizeModerator($request);
        abort_unless($evidence->report, 404);
        \App\Support\ModerationScope::authorizeReport($request->user(), $evidence->report);

        $data = $request->validate([
            'rects' => ['required', 'string', 'max:5000'],
        ]);

        $rects = json_decode($data['rects'], true);
        abort_unless(is_array($rects) && count($rects) >= 1 && count($rects) <= 30, 422);

        $clean = [];
        foreach ($rects as $r) {
            abort_unless(is_array($r), 422);
            foreach (['x', 'y', 'w', 'h'] as $k) {
                abort_unless(isset($r[$k]) && is_numeric($r[$k]), 422);
            }
            $x = max(0.0, min(1.0, (float) $r['x']));
            $y = max(0.0, min(1.0, (float) $r['y']));
            $w = max(0.0, min(1.0 - $x, (float) $r['w']));
            $h = max(0.0, min(1.0 - $y, (float) $r['h']));
            if ($w > 0 && $h > 0) {
                $clean[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
            }
        }
        abort_if($clean === [], 422);

        $done = app(\App\Services\EvidenceBlurService::class)->blur($evidence, $request->user(), $clean);

        if (! $done) {
            return back()->with('status', __('evblur.not_allowed'));
        }

        app(\App\Services\AuditService::class)->log($request->user(), 'evidence.blur', 'evidence', (string) $evidence->id, [
            'report_id' => $evidence->report_id,
            'boxes'     => count($clean),
        ]);

        return back()->with('status', __('evblur.done_msg'));
    }

    /** Moderators can view pending photos from private storage. */
    public function photo(Request $request, Evidence $evidence)
    {
        $this->authorizeModerator($request);

        \App\Support\ModerationScope::authorizeReport($request->user(), $evidence->report);

        $disk = $evidence->moderation === 'approved' ? ($evidence->public_disk ?: \App\Support\StorageSettings::publicDisk()) : ($evidence->private_disk ?: \App\Support\StorageSettings::privateDisk());
        abort_unless(Storage::disk($disk)->exists($evidence->public_path), 404);

        return Storage::disk($disk)->response($evidence->public_path, null, [
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeModerator(Request $request): void
    {
        abort_unless($request->user()?->canModerate(), 403);
    }
}