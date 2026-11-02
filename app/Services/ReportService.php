<?php

namespace App\Services;

use App\Models\{Issue, Report, StatusEvent, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportService
{
    public function __construct(
        private DuplicateService $duplicates,
        private EvidenceService  $evidence,
    ) {}

    /**
     * Submit a report.
     *
     * If $issueId is provided, attach to the existing issue.
     * Otherwise create a new issue.
     *
     * Both paths create a Report record and an initial StatusEvent.
     */
    public function submit(
        User   $user,
        float  $lat,
        float  $lng,
        int    $categoryId,
        string $title,
        string $description,
        ?string $roadName   = null,
        ?string $landmark   = null,
        ?int    $issueId    = null,   // attach to existing issue
        ?array  $files      = [],     // UploadedFile[]
    ): Report {
        return DB::transaction(function () use (
            $user, $lat, $lng, $categoryId, $title, $description,
            $roadName, $landmark, $issueId, $files
        ) {
            if ($issueId) {
                // ── Attach to existing issue ─────────────
                $issue = Issue::findOrFail($issueId);
            } else {
                // ── Create a new issue ───────────────────
                $issue = $this->createIssue(
                    $lat, $lng, $categoryId, $title, $roadName, $landmark
                );
            }

            // ── Create the report ────────────────────────
            $report = Report::create([
                'issue_id'    => $issue->id,
                'user_id'     => $user->id,
                'description' => $description,
                'lat'         => $lat,
                'lng'         => $lng,
                'moderation'  => 'pending',
            ]);

            // ── Attach evidence (photos) ─────────────────
            foreach ($files as $file) {
                $this->evidence->store(
                    $file,
                    $user->id,
                    reportId: $report->id,
                );
            }

            // ── Record the report in the issue timeline ──
            StatusEvent::create([
                'issue_id'    => $issue->id,
                'dimension'   => 'progress',
                'from_value'  => $issue->progress_status,
                'to_value'    => $issue->progress_status,  // no change, just logging
                'actor_id'    => $user->id,
                'actor_type'  => $user->role,
                'source_type' => 'platform',
                'reason'      => 'New report submitted.',
            ]);

            // ── Update counters ──────────────────────────
            $issue->increment('reports_count');
            $issue->update(['last_activity_at' => now()]);

            // ── Set first_reported_at on first report ────
            if ($issue->first_reported_at === null) {
                $issue->update(['first_reported_at' => now()]);
            }

            return $report;
        });
    }

    private function createIssue(
        float  $lat,
        float  $lng,
        int    $categoryId,
        string $title,
        ?string $roadName,
        ?string $landmark,
    ): Issue {
        $issue = Issue::create([
            'public_id'         => (string) Str::ulid(),
            'title'             => $title,
            'category_id'       => $categoryId,
            'lat'               => $lat,
            'lng'               => $lng,
            'road_name'         => $roadName,
            'landmark'          => $landmark,
            'progress_status'   => 'reported',
            'verification_status' => 'unverified',
            'disposition'       => 'active',
            'reports_count'     => 0,
            'first_reported_at' => now(),
            'last_activity_at'  => now(),
        ]);

        // Record issue creation as a status event
        StatusEvent::create([
            'issue_id'    => $issue->id,
            'dimension'   => 'progress',
            'from_value'  => null,
            'to_value'    => 'reported',
            'actor_id'    => auth()->id(),
            'actor_type'  => auth()->user()?->role ?? 'citizen',
            'source_type' => 'platform',
            'reason'      => 'Issue created from report.',
        ]);

        return $issue;
    }
}