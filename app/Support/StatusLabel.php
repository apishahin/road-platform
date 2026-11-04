<?php

namespace App\Support;

use App\Models\{Issue, StatusEvent};

class StatusLabel
{
    /** Plain-language explanation of the current state. */
    public static function explain(Issue $issue): string
    {
        $key = $issue->disposition === 'reopened' ? 'reopened' : $issue->progress_status;

        return __('issue.explain.' . $key);
    }

    /** Label for one timeline entry. */
    public static function forEvent(StatusEvent $event): string
    {
        // Entries that record a note (for example, a new report) have no change
        if ($event->from_value === $event->to_value) {
            return __('issue.page.event_note');
        }

        return match ($event->dimension) {
            'progress'     => __('issue.progress.' . $event->to_value),
            'verification' => __('issue.verification.' . $event->to_value),
            'disposition'  => __('issue.disposition.' . $event->to_value),
            default        => $event->to_value,
        };
    }

    /**
     * Display text for a stored event reason.
     *
     * Stored reasons are never changed (status events are append-only).
     * Known system-written English reasons are shown in the current language.
     * Anything written by a person is shown exactly as stored.
     */
    public static function reason(?string $stored): string
    {
        $text = trim((string) $stored);

        if ($text === '') {
            return '';
        }

        // 1. Exact match against known system reasons (lang/xx/timeline.php)
        $known = __('timeline.reasons');
        if (is_array($known) && is_string($known[$text] ?? null)) {
            return $known[$text];
        }

        // 2. DuplicateService: "Marked as duplicate of issue #ID. <moderator's own note>"
        //    The prefix is translated; the moderator's note is kept as written.
        if (preg_match('/^Marked as duplicate of issue #(.+?)\.(?:\s+(.*))?$/su', $text, $m)) {
            $line = __('timeline.duplicate_of', ['id' => $m[1]]);
            $note = trim($m[2] ?? '');

            return $note === '' ? $line : $line . ' ' . $note;
        }

        // 3. Unknown or human-written reason: show as stored
        return $text;
    }

    public static function source(string $sourceType): string
    {
        return __('issue.source.' . $sourceType);
    }

    public static function actor(string $actorType): string
    {
        return __('issue.actor.' . $actorType);
    }

    public static function verification(string $value): string
    {
        return __('issue.verification.' . $value);
    }
}