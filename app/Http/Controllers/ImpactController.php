<?php

namespace App\Http\Controllers;

use App\Models\{Category, Issue, StatusEvent};
use Illuminate\Support\Facades\DB;

class ImpactController extends Controller
{
    /** Public, read-only impact statistics page. */
    public function index()
    {
        $base = Issue::query()->whereNotNull('published_at');

        $total    = (clone $base)->count();
        $repaired = (clone $base)->where('progress_status', 'repaired')->count();
        $verified = (clone $base)->where('verification_status', 'verified')->count();
        $open     = (clone $base)->where('disposition', 'active')
            ->whereNotIn('progress_status', ['repaired'])->count();

        $rate = $total > 0 ? round($repaired / $total * 100) : 0;

        // Average days from first report to the "repaired" status event.
        $avgDays = null;
        $pairs = StatusEvent::query()
            ->join('issues', 'issues.id', '=', 'status_events.issue_id')
            ->where('status_events.dimension', 'progress')
            ->where('status_events.to_value', 'repaired')
            ->whereNotNull('issues.published_at')
            ->select(['issues.first_reported_at', 'status_events.created_at as fixed_at'])
            ->limit(500)
            ->get();
        if ($pairs->isNotEmpty()) {
            $sum = 0;
            $n   = 0;
            foreach ($pairs as $p) {
                if (! $p->first_reported_at || ! $p->fixed_at) {
                    continue;
                }
                $days = \Carbon\Carbon::parse($p->first_reported_at)->diffInDays(\Carbon\Carbon::parse($p->fixed_at));
                $sum += $days;
                $n++;
            }
            $avgDays = $n > 0 ? round($sum / $n, 1) : null;
        }

        // Districts with at least one published issue.
        $districts = (clone $base)->whereNotNull('admin_area_id')->distinct()->count('admin_area_id');

        // Last 6 months: reported vs repaired.
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $months[$m->format('Y-m')] = [
                'label'    => $m->format('M'),
                'reported' => 0,
                'fixed'    => 0,
            ];
        }
        $rep = (clone $base)
            ->where('first_reported_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', first_reported_at) as ym, COUNT(*) as c")
            ->groupBy('ym')->pluck('c', 'ym');
        foreach ($months as $mYm => &$mRow) {
            $mRow['reported'] = (int) ($rep[$mYm] ?? 0);
        }
        unset($mRow);

        $fix = StatusEvent::query()
            ->join('issues', 'issues.id', '=', 'status_events.issue_id')
            ->where('status_events.dimension', 'progress')
            ->where('status_events.to_value', 'repaired')
            ->whereNotNull('issues.published_at')
            ->where('status_events.created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', status_events.created_at) as ym, COUNT(*) as c")
            ->groupBy('ym')->pluck('c', 'ym');
        foreach ($months as $mYm => &$mRow) {
            $mRow['fixed'] = (int) ($fix[$mYm] ?? 0);
        }
        unset($mRow);

        // Top categories among published issues.
        $topCats = (clone $base)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as c')
            ->groupBy('category_id')->orderByDesc('c')->limit(5)
            ->pluck('c', 'category_id');
        $catNames = Category::whereIn('id', $topCats->keys()->all())->get()->keyBy('id');

        return view('impact', [
            'total'     => $total,
            'repaired'  => $repaired,
            'verified'  => $verified,
            'open'      => $open,
            'rate'      => $rate,
            'avgDays'   => $avgDays,
            'districts' => $districts,
            'months'    => $months,
            'topCats'   => $topCats,
            'catNames'  => $catNames,
        ]);
    }
}
