<?php

namespace App\Services;

use App\Models\{Issue, IssueFollow, StatusEvent, UserNotification};

/**
 * Creates in-app notifications for the followers of an issue after every
 * status change. Never throws — notification failure must not break the
 * status change itself.
 */
class FollowNotifier
{
    public static function dispatch(Issue $issue, StatusEvent $event, int $actorId): void
    {
        try {
            $userIds = IssueFollow::where('issue_id', $issue->id)->pluck('user_id')->all();
            $userIds = array_values(array_unique(array_diff($userIds, [$actorId])));
            if ($userIds === []) {
                return;
            }

            $url = \App\Support\IssuePresenter::url($issue);
            $now = now();
            $rows = [];
            foreach ($userIds as $uid) {
                $rows[] = [
                    'user_id'         => $uid,
                    'issue_id'        => $issue->id,
                    'status_event_id' => $event->id,
                    'title'           => mb_substr((string) $issue->title, 0, 190),
                    'url'             => $url,
                    'created_at'      => $now,
                ];
            }
            UserNotification::insert($rows);

            // Web push to the same users (fail-soft, no-op unless enabled)
            $label = \App\Support\StatusLabel::forEvent($event);
            \App\Services\PushService::sendToUsers($userIds, (string) $issue->title, $label, $url);
        } catch (\Throwable) {
            // fail soft: notifications are best-effort
        }
    }
}
