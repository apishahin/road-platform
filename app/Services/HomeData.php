<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Report;
use App\Support\IssuePresenter;

/** Home page data in one place, so a mobile API can reuse it later. */
class HomeData
{
    public function build(): array
    {
        // Latest active issues, newest activity first
        $issues = Issue::query()
            ->active()
            ->with('category')
            ->latest('last_activity_at')
            ->limit(config('roadplatform.map.max_markers'))
            ->get();

        $repaired = Issue::query()
            ->active()
            ->where('progress_status', 'repaired')
            ->with('category')
            ->latest('last_activity_at')
            ->limit(3)
            ->get();

        return [
            'recent' => $issues->take(6),
            'mapIssues' => $issues->map(fn ($i) => IssuePresenter::map($i))->values(),
            'repaired' => $repaired,
            'stats' => [
                'open' => Issue::active()->where('progress_status', '!=', 'repaired')->count(),
                'repaired' => Issue::active()->where('progress_status', 'repaired')->count(),
                'reports' => Report::where('moderation', 'approved')->count(),
            ],
        ];
    }
}