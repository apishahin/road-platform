<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Report;
use App\Models\StatusEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(private EvidenceService $evidence)
    {
    }

    /**
     * Submit a report as a PENDING claim.
     * - New issue: created hidden (published_at = null).
     * - Existing issue: must already be public. Counters and activity change only on approval.
     */
    public function submit(
        User $user,
        float $lat,
        float $lng,
        ?int $categoryId,
        ?string $title,
        string $description,
        ?string $roadName = null,
        ?string $landmark = null,
        ?string $issuePublicId = null,
        array $files = [],
    ): Report {
        return DB::transaction(function () use (
            $user, $lat, $lng, $categoryId, $title, $description,
            $roadName, $landmark, $issuePublicId, $files
        ) {
            if ($issuePublicId) {
                $issue = Issue::active()->where('public_id', $issuePublicId)->first();
                if (! $issue) {
                    throw ValidationException::withMessages([
                        'issue_id' => __('report.issue_gone'),
                    ]);
                }
            } else {
                $issue = $this->createHiddenIssue($user, $lat, $lng, (int) $categoryId, (string) $title, $roadName, $landmark);
            }

            $report = Report::create([
                'issue_id'    => $issue->id,
                'user_id'     => $user->id,
                'description' => $description,
                'lat'         => $lat,
                'lng'         => $lng,
                'moderation'  => 'pending',
            ]);

            foreach ($files as $file) {
                $this->evidence->store($file, $user->id, reportId: $report->id);
            }

            return $report;
        });
    }

    private function createHiddenIssue(
        User $user,
        float $lat,
        float $lng,
        int $categoryId,
        string $title,
        ?string $roadName,
        ?string $landmark,
    ): Issue {
        $issue = new Issue();
        $issue->forceFill([
            'title'               => $title,
            'category_id'         => $categoryId,
            'lat'                 => $lat,
            'lng'                 => $lng,
            'road_name'           => $roadName,
            'landmark'            => $landmark,
            'progress_status'     => 'reported',
            'verification_status' => 'unverified',
            'disposition'         => 'active',
            'reports_count'       => 0,
            'first_reported_at'   => now(),
            'last_activity_at'    => now(),
            'published_at'        => null,
        ])->save();

        // Not visible publicly until the issue is published.
        (new StatusEvent())->forceFill([
            'issue_id'    => $issue->id,
            'dimension'   => 'progress',
            'from_value'  => null,
            'to_value'    => 'reported',
            'actor_id'    => $user->id,
            'actor_type' => 'citizen',
            'source_type' => 'platform',
            'reason'      => 'Issue created from a community report.',
        ])->save();

        return $issue;
    }
}