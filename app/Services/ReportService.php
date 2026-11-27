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
     * Submit a report as a pending claim. Text-only submissions are kept out
     * of the public map and routing pipeline until a reviewer supplies a
     * verified Bangladesh point.
     */
    public function submit(
        User $user,
        ?float $lat,
        ?float $lng,
        string $locationPrecision,
        ?string $placeName,
        ?string $locationNote,
        ?int $categoryId,
        ?string $title,
        string $description,
        ?string $roadName = null,
        ?string $landmark = null,
        ?string $issuePublicId = null,
        array $files = [],
        ?int $suggestedAreaId = null,
    ): Report {
        return DB::transaction(function () use (
            $user, $lat, $lng, $locationPrecision, $placeName, $locationNote,
            $categoryId, $title, $description, $roadName, $landmark,
            $issuePublicId, $files, $suggestedAreaId
        ) {
            if ($issuePublicId) {
                $issue = Issue::active()->where('public_id', $issuePublicId)->first();
                if (! $issue) {
                    throw ValidationException::withMessages(['issue_id' => __('report.issue_gone')]);
                }
            } else {
                $issue = $this->createHiddenIssue(
                    $user, $lat, $lng, $locationPrecision, $placeName, $locationNote,
                    (int) $categoryId, (string) $title, $roadName, $landmark
                );
            }

            $report = Report::create([
                'issue_id' => $issue->id,
                'user_id' => $user->id,
                'description' => $description,
                'lat' => $lat,
                'lng' => $lng,
                'location_precision' => $locationPrecision,
                'place_name' => $placeName,
                'location_note' => $locationNote,
                'moderation' => 'pending',
                'suggested_area_id' => $suggestedAreaId,
            ]);

            foreach ($files as $file) {
                $this->evidence->store($file, $user->id, reportId: $report->id);
            }

            return $report;
        });
    }

    private function createHiddenIssue(
        User $user,
        ?float $lat,
        ?float $lng,
        string $locationPrecision,
        ?string $placeName,
        ?string $locationNote,
        int $categoryId,
        string $title,
        ?string $roadName,
        ?string $landmark,
    ): Issue {
        $needsReview = $locationPrecision === 'text_only';
        $issue = new Issue();
        $issue->forceFill([
            'title' => $title,
            'category_id' => $categoryId,
            'lat' => $lat,
            'lng' => $lng,
            'location_precision' => $locationPrecision,
            'location_review_status' => $needsReview ? Issue::LOCATION_REVIEW : Issue::LOCATION_READY,
            'road_name' => $roadName,
            'landmark' => $landmark,
            'place_name' => $placeName,
            'location_note' => $locationNote,
            'progress_status' => 'reported',
            'verification_status' => 'unverified',
            'disposition' => 'active',
            'reports_count' => 0,
            'first_reported_at' => now(),
            'last_activity_at' => now(),
            'published_at' => null,
        ])->save();

        (new StatusEvent())->forceFill([
            'issue_id' => $issue->id,
            'dimension' => 'progress',
            'from_value' => null,
            'to_value' => 'reported',
            'actor_id' => $user->id,
            'actor_type' => 'citizen',
            'source_type' => 'platform',
            'reason' => $needsReview
                ? 'Issue created from a text-only community report; location review is required.'
                : 'Issue created from a community report.',
        ])->save();

        return $issue;
    }
}
