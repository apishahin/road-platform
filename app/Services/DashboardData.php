<?php

namespace App\Services;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use App\Models\Report;

/**
 * Read-model for the public transparency dashboard (/dashboard).
 * All numbers come straight from the live database — nothing is edited by hand.
 */
class DashboardData
{
    public function build(): array
    {
        return [
            'totals'     => $this->totals(),
            'districts'  => $this->byDistrict(),
            'months'     => $this->byMonth(),
            'categories' => $this->byCategory(),
        ];
    }

    /** Headline counters. */
    private function totals(): array
    {
        return [
            'reports'   => Report::where('moderation', 'approved')->count(),
            'active'    => Issue::active()->count(),
            'repaired'  => Issue::active()->where('progress_status', 'repaired')->count(),
            'districts' => Issue::activeDistrictCount(),
        ];
    }

    /**
     * Per-district totals. Issues may be pinned to any admin level, so each
     * pin is walked up the tree to its district ancestor before counting.
     *
     * @return array<int, array{name: string, total: int, repaired: int, pct: int}>
     */
    private function byDistrict(): array
    {
        $pins = Issue::active()
            ->whereNotNull('admin_area_id')
            ->get(['id', 'admin_area_id', 'progress_status']);

        if ($pins->isEmpty()) {
            return [];
        }

        $levels  = AdminArea::query()->pluck('level', 'id');
        $parents = AdminArea::query()->pluck('parent_id', 'id');

        $agg = [];
        foreach ($pins as $pin) {
            $cur = (int) $pin->admin_area_id;
            for ($i = 0; $i < 8 && $cur > 0; $i++) {
                if (($levels[$cur] ?? null) === 'district') {
                    $agg[$cur]['total'] = ($agg[$cur]['total'] ?? 0) + 1;
                    if ($pin->progress_status === 'repaired') {
                        $agg[$cur]['repaired'] = ($agg[$cur]['repaired'] ?? 0) + 1;
                    }
                    break;
                }
                $cur = (int) ($parents[$cur] ?? 0);
            }
        }

        if ($agg === []) {
            return [];
        }

        $isBn   = app()->getLocale() === 'bn';
        $names  = AdminArea::whereIn('id', array_keys($agg))
            ->get(['id', 'name_bn', 'name_en'])
            ->mapWithKeys(fn ($a) => [$a->id => $isBn ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn)]);

        $rows = [];
        foreach ($agg as $id => $c) {
            $total    = (int) ($c['total'] ?? 0);
            $repaired = (int) ($c['repaired'] ?? 0);
            $rows[] = [
                'name'     => $names[$id] ?? '—',
                'total'    => $total,
                'repaired' => $repaired,
                'pct'      => $total > 0 ? (int) round($repaired * 100 / $total) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * Active issues first reported in each of the last 12 months
     * (bucketed in PHP so sqlite/MySQL both work).
     *
     * @return array<int, array{label: string, n: int}>
     */
    private function byMonth(): array
    {
        $start = now()->startOfMonth()->subMonths(11);

        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $buckets[$m->format('Y-m')] = ['label' => $m->format('n'), 'n' => 0]; // label = month number, view translates
        }

        Issue::active()
            ->whereNotNull('first_reported_at')
            ->where('first_reported_at', '>=', $start)
            ->pluck('first_reported_at')
            ->each(function ($at) use (&$buckets) {
                $key = \Carbon\Carbon::parse($at)->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['n']++;
                }
            });

        return array_values($buckets);
    }

    /** Top categories of active issues. */
    private function byCategory(): array
    {
        $counts = Issue::active()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $isBn  = app()->getLocale() === 'bn';
        $names = Category::whereIn('id', $counts->keys())
            ->get(['id', 'name_bn', 'name_en'])
            ->mapWithKeys(fn ($c) => [$c->id => $isBn ? ($c->name_bn ?: $c->name_en) : ($c->name_en ?: $c->name_bn)]);

        $rows = [];
        foreach ($counts as $id => $c) {
            $rows[] = ['name' => $names[$id] ?? '—', 'n' => (int) $c];
        }
        usort($rows, fn ($a, $b) => $b['n'] <=> $a['n']);

        return array_slice($rows, 0, 8);
    }
}
