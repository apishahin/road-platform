<?php

namespace App\Support;

use App\Models\IssueNotification;

/**
 * Marks issues that an authority was really notified about.
 * Only notifications with status "sent" count. "logged", "failed" and "opened" never do.
 */
class IssueNotice
{
    public static function mark(iterable $issues): void
    {
        $items = collect($issues)->values();
        $ids = $items->pluck('id')->unique()->values()->all();

        $sent = $ids === []
            ? []
            : IssueNotification::query()
                ->where('status', 'sent')
                ->whereIn('issue_id', $ids)
                ->pluck('issue_id')
                ->flip()
                ->all();

        foreach ($items as $issue) {
            $issue->setAttribute('authority_notified', isset($sent[$issue->id]));
        }
    }
}