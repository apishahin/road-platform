<?php

namespace App\Services;

use App\Models\{Issue, StatusEvent, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IssueStatusService
{
    /** Allowed values per dimension */
    private const ALLOWED = [
        'progress'     => ['reported', 'acknowledged', 'planned',
                           'work_started', 'completion_reported', 'repaired'],
        'verification' => ['unverified', 'community_confirmed',
                           'reviewed', 'verified'],
        'disposition'  => ['active', 'duplicate', 'rejected',
                           'referred', 'reopened'],
    ];

    /** Map dimension → column name */
    private const COLUMN = [
        'progress'     => 'progress_status',
        'verification' => 'verification_status',
        'disposition'  => 'disposition',
    ];

    /** Allowed source types */
    private const SOURCES = ['platform', 'official_letter', 'news',
                             'public_notice', 'site_visit', 'phone_call'];

    /**
     * Record a status change.
     * Always appends a StatusEvent and updates the issue's column.
     * Never modifies or deletes previous events.
     */
    public function change(
        Issue   $issue,
        string  $dimension,
        string  $toValue,
        User    $actor,
        string  $reason,
        string  $sourceType = 'platform',
        ?string $sourceUrl  = null,
    ): StatusEvent {
        // ── Validate dimension ──────────────────────────
        if (! array_key_exists($dimension, self::ALLOWED)) {
            throw new InvalidArgumentException("Unknown dimension: {$dimension}");
        }

        // ── Validate value ──────────────────────────────
        if (! in_array($toValue, self::ALLOWED[$dimension], true)) {
            throw new InvalidArgumentException(
                "Invalid {$dimension} value: {$toValue}. Allowed: " .
                implode(', ', self::ALLOWED[$dimension])
            );
        }

        // ── Validate reason ─────────────────────────────
        if (trim($reason) === '') {
            throw new InvalidArgumentException(
                'A reason is required for every status change.'
            );
        }

        // ── Validate source type ────────────────────────
        if (! in_array($sourceType, self::SOURCES, true)) {
            throw new InvalidArgumentException("Unknown source type: {$sourceType}");
        }

        // ── Rule: "repaired" requires prior verification ─
        if ($dimension === 'progress'
            && $toValue === 'repaired'
            && $issue->verification_status !== 'verified'
        ) {
            throw new InvalidArgumentException(
                'An issue can be marked "repaired" only after the repair has been ' .
                'verified. Current verification: "' . $issue->verification_status . '".'
            );
        }

        // ── Rule: cannot go backward in progress ────────
        if ($dimension === 'progress' && $this->isBackward($issue, $toValue)) {
            throw new InvalidArgumentException(
                'Progress cannot go backwards from "' . $issue->progress_status .
                '" to "' . $toValue . '".'
            );
        }

        // ── Create the event atomically ─────────────────
        return DB::transaction(function () use (
            $issue, $dimension, $toValue, $actor, $reason, $sourceType, $sourceUrl
        ) {
            $column = self::COLUMN[$dimension];
            $from   = $issue->{$column};

            $event = StatusEvent::create([
                'issue_id'    => $issue->id,
                'dimension'   => $dimension,
                'from_value'  => $from,
                'to_value'    => $toValue,
                'actor_id'    => $actor->id,
                'actor_type'  => $actor->role === 'admin' ? 'moderator' : $actor->role,
                'source_type' => $sourceType,
                'source_url'  => $sourceUrl,
                'reason'      => trim($reason),
            ]);

            $issue->update([
                $column            => $toValue,
                'last_activity_at' => now(),
            ]);

            return $event;
        });
    }

    /**
     * Check whether moving to $toValue would be backward in progress.
     */
    private function isBackward(Issue $issue, string $toValue): bool
    {
        $order = array_flip(self::ALLOWED['progress']);
        $current = $order[$issue->progress_status] ?? 0;
        $target  = $order[$toValue] ?? 0;

        return $target < $current;
    }
}