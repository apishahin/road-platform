<?php

namespace App\Http\Controllers;

use App\Models\AdminArea;
use App\Models\Evidence;
use App\Models\Issue;
use App\Models\Observation;
use App\Models\StatusEvent;
use App\Services\AuditService;
use App\Services\EvidenceBlurService;
use App\Services\ModerationService;
use App\Support\ModerationScope;
use App\Support\StorageSettings;
use App\Support\UpdatePhotos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Review of photos sent with citizen updates and on-site checks. */
class PhotoReviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->canModerate(), 403);

        $q = Evidence::query()
            ->where('moderation', 'pending')
            ->where(function ($w) {
                $w->whereNotNull('observation_id')->orWhereNotNull('status_event_id');
            });

        $ids = ModerationScope::areaIds($user);
        if ($ids !== null) {
            if (! $ids) {
                $q->whereRaw('1 = 0');
            } else {
                $issueIds = Issue::query()->select('id')->whereIn('admin_area_id', $ids);
                $q->where(function ($w) use ($issueIds) {
                    $w->whereIn('observation_id', Observation::query()->select('id')->whereIn('issue_id', $issueIds))
                      ->orWhereIn('status_event_id', StatusEvent::query()->select('id')->whereIn('issue_id', $issueIds));
                });
            }
        }

        $total = (clone $q)->count();
        $items = $q->orderBy('id')->limit(30)->get();
        $items->load('statusEvent.issue');
        $obsMap = Observation::with('issue')
            ->whereIn('id', $items->pluck('observation_id')->filter()->all())
            ->get()->keyBy('id');

        $cache = [];
        $rows = $items->map(function ($ev) use ($obsMap) {
            if ($ev->observation_id) {
                $o = $obsMap->get($ev->observation_id);
                return ['ev' => $ev, 'issue' => $o?->issue, 'kind' => 'citizen', 'label' => $o ? __('issues.obs_' . $o->type) : ''];
            }

            return ['ev' => $ev, 'issue' => $ev->statusEvent?->issue, 'kind' => 'visit', 'label' => ''];
        })->filter(fn ($r) => $r['issue'])->values()->map(function ($r) use (&$cache) {
            $r['issue']->loadMissing(['adminArea', 'category']);
            $r['area'] = $this->areaPath($r['issue']->adminArea, $cache);

            return $r;
        });

        return view('issues.photo-review', ['rows' => $rows, 'total' => $total]);
    }

    public function image(Request $request, Evidence $evidence)
    {
        $this->authorizeEvidence($request, $evidence);

        $disk = $evidence->moderation === 'approved'
            ? ($evidence->public_disk ?: StorageSettings::publicDisk())
            : ($evidence->private_disk ?: StorageSettings::privateDisk());
        abort_unless(Storage::disk($disk)->exists($evidence->public_path), 404);

        return Storage::disk($disk)->response($evidence->public_path, null, [
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, Evidence $evidence)
    {
        $this->authorizeEvidence($request, $evidence);
        abort_unless($evidence->moderation === 'pending', 404);

        app(ModerationService::class)->publishEvidence($evidence);
        app(AuditService::class)->log($request->user(), 'evidence.publish', 'evidence', (string) $evidence->id, []);

        return back()->with('status', __('issues.photo_review_approved_msg'));
    }

    public function hide(Request $request, Evidence $evidence)
    {
        $this->authorizeEvidence($request, $evidence);

        $data = $request->validate([
            'reason' => ['required', 'in:face,plate,personal,other'],
        ]);

        $done = app(ModerationService::class)->hideEvidence($evidence, $request->user(), $data['reason']);
        if ($done) {
            app(AuditService::class)->log($request->user(), 'evidence.hide', 'evidence', (string) $evidence->id, [
                'reason' => $data['reason'],
            ]);
        }

        return back()->with('status', __('issues.photo_review_hidden_msg'));
    }

    /** Cover parts of a pending photo (blur or black). The clear original is deleted. */
    public function blur(Request $request, Evidence $evidence)
    {
        $this->authorizeEvidence($request, $evidence);

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
                $clean[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'm' => (($r['m'] ?? 'blur') === 'black') ? 'black' : 'blur'];
            }
        }
        abort_if($clean === [], 422);

        try {
            $done = app(EvidenceBlurService::class)->blur($evidence, $request->user(), $clean);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['photo' => __('issues.photo_blur_failed')]);
        }

        if (! $done) {
            return back()->with('status', __('issues.photo_blur_not_allowed'));
        }

        app(AuditService::class)->log($request->user(), 'evidence.blur', 'evidence', (string) $evidence->id, [
            'boxes' => count($clean),
            'black' => count(array_filter($clean, fn ($c) => $c['m'] === 'black')),
        ]);

        return back()->with('status', __('issues.photo_blur_done'));
    }

    /** Admin only: turn update photos on or off. */
    public function toggle(Request $request, AuditService $audit)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $data = $request->validate(['enabled' => ['required', 'in:0,1']]);
        $on = (string) $data['enabled'] === '1';
        UpdatePhotos::set($on);
        $audit->log($request->user(), 'update_photos.changed', 'setting', 'update_photos', ['enabled' => $on]);

        return redirect()->route('admin.storage.index')
            ->with('status', $on ? __('issues.photo_switch_on') : __('issues.photo_switch_off'));
    }

    private function authorizeEvidence(Request $request, Evidence $evidence): Issue
    {
        $user = $request->user();
        abort_unless($user && $user->canModerate(), 403);

        $issue = null;
        if ($evidence->observation_id) {
            $issue = Observation::find($evidence->observation_id)?->issue;
        } elseif ($evidence->status_event_id) {
            $issue = StatusEvent::find($evidence->status_event_id)?->issue;
        }
        abort_unless($issue, 404);
        abort_unless(IssueVerifyController::canVerify($user, $issue), 403);

        return $issue;
    }

    /** "Division → District → ... → Union" for the issue's confirmed area. */
    private function areaPath(?AdminArea $area, array &$cache): string
    {
        if (! $area) {
            return '';
        }

        $parts = [];
        $cur = $area;
        for ($i = 0; $cur && $i < 7; $i++) {
            $a = $cur->getAttributes();
            $parts[] = (string) ($a['name_bn'] ?? ($a['name_en'] ?? ''));
            $pid = $a['parent_id'] ?? null;
            if (! $pid) {
                break;
            }
            if (! array_key_exists($pid, $cache)) {
                $cache[$pid] = AdminArea::find($pid);
            }
            $cur = $cache[$pid];
        }

        return implode(' → ', array_reverse(array_filter($parts)));
    }
}