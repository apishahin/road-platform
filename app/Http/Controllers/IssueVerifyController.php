<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\StatusEvent;
use App\Models\User;
use App\Services\EvidenceService;
use App\Services\IssueStatusService;
use App\Support\ModerationScope;
use App\Support\UpdatePhotos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IssueVerifyController extends Controller
{
    /**
     * Moderators/admins: any issue. Volunteers: only issues inside their own areas.
     * An issue without a confirmed area can be checked by moderators only.
     */
    public static function canVerify(?User $user, Issue $issue): bool
    {
        if (! $user || ! $user->canModerate()) {
            return false;
        }

        $ids = ModerationScope::areaIds($user);
        if ($ids === null) {
            return true;
        }

        return $issue->admin_area_id !== null && in_array((int) $issue->admin_area_id, $ids, true);
    }

    /** Record a site visit. 'repaired' is set only here, through IssueStatusService. */
    public function store(Request $request, string $publicId, IssueStatusService $status, EvidenceService $evidence)
    {
        $issue = Issue::where('public_id', $publicId)->firstOrFail();

        abort_unless(
            $issue->published_at !== null && in_array($issue->disposition, ['active', 'reopened'], true),
            404
        );
        abort_unless(self::canVerify($request->user(), $issue), 403);

        $data = $request->validate([
            'type' => ['required', 'in:repaired,still_exists'],
            'note' => ['nullable', 'string', 'max:300'],
            'seen' => ['accepted'],
        ]);

        $actor  = $request->user();
        // Status events always need an auditable reason; a blank staff comment is
        // represented by this neutral system text rather than blocking the update.
        $reason = trim((string) ($data['note'] ?? '')) ?: __('issues.verify_no_note');
        $before = (int) StatusEvent::where('issue_id', $issue->id)->max('id');

        DB::transaction(function () use ($status, $issue, $actor, $reason, $data) {
            if ($data['type'] === 'repaired') {
                $status->change($issue, 'verification', 'verified', $actor, $reason, 'site_visit');

                if ($issue->progress_status !== 'repaired') {
                    $status->change($issue, 'progress', 'repaired', $actor, $reason, 'site_visit');
                }
                if ($issue->disposition === 'reopened') {
                    $status->change($issue, 'disposition', 'active', $actor, $reason, 'site_visit');
                }
            } else {
                $to = in_array($issue->verification_status, ['reviewed', 'verified'], true)
                    ? $issue->verification_status
                    : 'reviewed';
                $status->change($issue, 'verification', $to, $actor, $reason, 'site_visit');

                if ($issue->progress_status === 'repaired' && $issue->disposition !== 'reopened') {
                    $status->change($issue, 'disposition', 'reopened', $actor, $reason, 'site_visit');
                }
            }
        });

        $redirect = redirect(\App\Support\IssuePresenter::url($issue) . '#verify')->with('verify_done', true);

        $photo = UpdatePhotos::enabled()
            ? ($request->file('photo_camera') ?: $request->file('photo_gallery'))
            : null;

        if ($photo) {
            $redirect->with('photo_msg', $this->attachPhoto($evidence, $photo, $issue, $before, $actor->id));
        }

        return $redirect;
    }

    private function attachPhoto(EvidenceService $svc, $photo, Issue $issue, int $before, int $userId): array
    {
        $event = StatusEvent::where('issue_id', $issue->id)->where('id', '>', $before)
                    ->where('dimension', 'verification')->orderByDesc('id')->first()
              ?: StatusEvent::where('issue_id', $issue->id)->where('id', '>', $before)
                    ->orderByDesc('id')->first();

        if (! $event) {
            return ['for' => 'verify', 'ok' => false, 'text' => __('issues.photo_failed')];
        }

        try {
            $svc->store($photo, $userId, null, $event->id);
        } catch (\Throwable $e) {
            return ['for' => 'verify', 'ok' => false, 'text' => __('issues.photo_failed')];
        }

        return ['for' => 'verify', 'ok' => true, 'text' => __('issues.photo_pending')];
    }

    /** Issues where citizens say "fixed" but nobody has checked on site yet. */
    public function queue(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->canModerate(), 403);

        $ids = ModerationScope::areaIds($user);

        $q = Issue::query()
            ->active()
            ->where('progress_status', '!=', 'repaired')
            ->whereHas('observations', fn ($o) => $o->where('type', 'fixed'))
            ->withCount(['observations as fixed_count' => fn ($o) => $o->where('type', 'fixed')])
            ->with(['category', 'adminArea']);

        if ($ids !== null) {
            $q->whereIn('admin_area_id', $ids);
        }

        $issues = $q->orderByDesc('fixed_count')->orderByDesc('last_activity_at')->paginate(20);

        return view('issues.verify-queue', ['issues' => $issues]);
    }
}