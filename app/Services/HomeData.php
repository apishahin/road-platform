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
        $data = $this->buildRaw();

        $ids = collect($data['recent'])->merge($data['repaired'])->pluck('id')->unique()->values()->all();

        $sent = $ids === []
            ? []
            : \App\Models\IssueNotification::query()
                ->where('status', 'sent')
                ->whereIn('issue_id', $ids)
                ->pluck('issue_id')
                ->flip()
                ->all();

        foreach ([$data['recent'], $data['repaired']] as $list) {
            foreach ($list as $issue) {
                $issue->setAttribute('authority_notified', isset($sent[$issue->id]));
            }
        }

        $data['stats']['notified'] = \App\Models\IssueNotification::query()
            ->where('status', 'sent')
            ->whereIn('issue_id', \App\Models\Issue::active()->select('id'))
            ->distinct()
            ->count('issue_id');

        return $data;
    }

    private function buildRaw(): array
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