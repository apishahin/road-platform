<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\IssueNotification;
use App\Models\Report;
use App\Support\IssueNotice;
use App\Support\IssuePresenter;

/** Home page data in one place, so a mobile API can reuse it later. */
class HomeData
{
    public function build(): array
    {
        // Latest active issues, newest activity first
        $issues = Issue::query()
            ->active()
            ->with(['category', 'adminArea'])
            ->latest('last_activity_at')
            ->limit(config('roadplatform.map.max_markers'))
            ->get();

        $repaired = Issue::query()
            ->active()
            ->where('progress_status', 'repaired')
            ->with(['category', 'adminArea'])
            ->latest('last_activity_at')
            ->limit(8)
            ->get();

        // Must happen before the map data is built, so the flag is included
        IssueNotice::mark($issues);
        IssueNotice::mark($repaired);

        // Latest approved photo per visible issue (recent list + repair cards).
        $ids = $issues->take(7)->pluck('id')->merge($repaired->pluck('id'))->unique()->all();
        $photos = [];
        try {
            $evs = \App\Models\Evidence::query()
                ->where('moderation', 'approved')
                ->whereHas('report', fn ($q) => $q->whereIn('issue_id', $ids))
                ->with('report:id,issue_id')
                ->latest('id')
                ->limit(60)
                ->get();
            foreach ($evs as $ev) {
                $iid = $ev->report?->issue_id;
                if ($iid && ! isset($photos[$iid])) {
                    $photos[$iid] = $ev->url();
                }
            }
        } catch (\Throwable $e) {
            $photos = []; // never break the home page for a thumbnail
        }

        return [
            'recent' => $issues->take(7),
            'photos' => $photos,
            'mapIssues' => $issues->map(fn ($i) => IssuePresenter::map($i))->values(),
            'repaired' => $repaired,
            'stats' => [
                'open' => Issue::active()->where('progress_status', '!=', 'repaired')->count(),
                'repaired' => Issue::active()->where('progress_status', 'repaired')->count(),
                'reports' => Report::where('moderation', 'approved')->count(),
                'notified' => IssueNotification::query()
                    ->where('status', 'sent')
                    ->whereIn('issue_id', Issue::active()->select('id'))
                    ->distinct()
                    ->count('issue_id'),
            ],
        ];
    }
}