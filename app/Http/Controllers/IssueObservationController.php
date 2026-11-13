<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\Issue;
use App\Models\Observation;
use App\Services\EvidenceService;
use App\Support\UpdatePhotos;
use Illuminate\Http\Request;

class IssueObservationController extends Controller
{
    private const MAX_PHOTOS = 3;

    /**
     * A signed-in citizen says what the problem looks like now.
     * This is only a claim: it never changes the issue status.
     * One answer per user per issue (a new answer replaces the old one).
     * An optional photo waits for approval before anyone can see it.
     */
    public function store(Request $request, string $publicId, EvidenceService $evidence)
    {
        $issue = Issue::where('public_id', $publicId)->firstOrFail();

        abort_unless(
            $issue->published_at !== null && in_array($issue->disposition, ['active', 'reopened'], true),
            404
        );

        $data = $request->validate([
            'type' => ['required', 'in:fixed,still_exists,got_worse'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $obs = Observation::updateOrCreate(
            ['issue_id' => $issue->id, 'user_id' => $request->user()->id],
            ['type' => $data['type'], 'note' => $data['note'] ?? null]
        );

        $redirect = redirect(url('/issues/' . $issue->public_id) . '#confirm')->with('obs_done', true);

        $photo = UpdatePhotos::enabled()
            ? ($request->file('photo_camera') ?: $request->file('photo_gallery'))
            : null;

        if ($photo) {
            $redirect->with('photo_msg', $this->attachPhoto($evidence, $photo, $obs, $request->user()->id));
        }

        return $redirect;
    }

    private function attachPhoto(EvidenceService $svc, $photo, Observation $obs, int $userId): array
    {
        if (Evidence::where('observation_id', $obs->id)->count() >= self::MAX_PHOTOS) {
            return ['for' => 'observe', 'ok' => false, 'text' => __('issues.photo_limit')];
        }

        try {
            $ev = $svc->store($photo, $userId);
            $ev->forceFill(['observation_id' => $obs->id])->save();
        } catch (\Throwable $e) {
            return ['for' => 'observe', 'ok' => false, 'text' => __('issues.photo_failed')];
        }

        return ['for' => 'observe', 'ok' => true, 'text' => __('issues.photo_pending')];
    }
}