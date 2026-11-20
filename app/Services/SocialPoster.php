<?php

namespace App\Services;

use App\Models\{Issue, StatusEvent};
use App\Support\ShareSettings;
use Illuminate\Support\Facades\Http;

/**
 * Posts to the configured Facebook page when an issue is marked repaired.
 * Fail-soft; never affects the request.
 */
class SocialPoster
{
    public static function afterStatusChange(Issue $issue, StatusEvent $event): void
    {
        try {
            if ($event->dimension !== 'progress' || $event->to_value !== 'repaired') {
                return;
            }
            $s = ShareSettings::all();
            if (! $s['on'] || $s['fb_page_id'] === '') {
                return;
            }
            $token = ShareSettings::token();
            if ($token === '') {
                return;
            }

            Http::timeout(12)->post(
                'https://graph.facebook.com/v21.0/' . $s['fb_page_id'] . '/feed',
                [
                    'message'      => __('issues.share_repaired_msg', ['title' => $issue->title]),
                    'link'         => url('/issues/' . $issue->public_id),
                    'access_token' => $token,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
