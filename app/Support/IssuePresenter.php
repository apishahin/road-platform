<?php

namespace App\Support;

use App\Models\Issue;

class IssuePresenter
{
    /** Label and colour for an issue. The colour is a hex string. */
    public static function display(Issue $issue): array
    {
        if ($issue->disposition === 'reopened') {
            return ['label' => __('Reopened'), 'color' => '#e11d48'];
        }

        return match ($issue->progress_status) {
            'acknowledged'        => ['label' => __('Acknowledged'), 'color' => '#d97706'],
            'planned'             => ['label' => __('Planned'), 'color' => '#2563eb'],
            'work_started'        => ['label' => __('Work started'), 'color' => '#4f46e5'],
            'completion_reported' => ['label' => __('Completion claimed (not yet verified)'), 'color' => '#9333ea'],
            'repaired'            => ['label' => __('Verified repaired'), 'color' => '#16a34a'],
            default               => ['label' => __('Reported'), 'color' => '#dc2626'],
        };
    }

    /** Data used by the map and by the nearby API (also for Flutter later). */
    public static function map(Issue $issue): array
    {
        $d = self::display($issue);

        return [
            'id'       => $issue->public_id,
            'title'    => $issue->title,
            'lat'      => (float) $issue->lat,
            'lng'      => (float) $issue->lng,
            'color'    => $d['color'],
            'status'   => $d['label'],
            'category' => $issue->category?->name(),
            'url'      => url('/issues/' . $issue->public_id),
            'group'    => self::group($issue),
            'meters'   => isset($issue->meters) ? (int) round($issue->meters) : null,
        ];
    }

    /** Filter group used by the home page chips: new, started or repaired. */
    public static function group(Issue $issue): string
    {
        if ($issue->disposition === 'reopened') {
            return 'new';
        }

        return match ($issue->progress_status) {
            'work_started', 'completion_reported' => 'started',
            'repaired' => 'repaired',
            default => 'new',
        };
    }

    /** Bangla digits when the language is Bangla. */
    public static function num(int|string $n): string
    {
        $s = (string) $n;

        return app()->getLocale() === 'bn'
            ? strtr($s, [
                '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
                '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
            ])
            : $s;
    }
}