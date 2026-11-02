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