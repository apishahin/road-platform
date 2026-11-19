<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin → Analytics: privacy-friendly visitor stats (see TrackVisit). */
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $today = now()->toDateString();

        // Date range (?from=YYYY-MM-DD&to=YYYY-MM-DD), default = last 30 days.
        $from = now()->subDays(29)->startOfDay();
        $to   = now()->startOfDay();
        $fIn  = (string) $request->query('from', '');
        $tIn  = (string) $request->query('to', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fIn) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tIn)) {
            try {
                $f = \Carbon\Carbon::createFromFormat('Y-m-d', $fIn)->startOfDay();
                $t = \Carbon\Carbon::createFromFormat('Y-m-d', $tIn)->startOfDay();
                if ($f->lte($t) && $t->lte(now()) && $f->gte(now()->subDays(180))) {
                    $from = $f;
                    $to   = $t;
                }
            } catch (\Throwable) {
                // bad calendar date (e.g. 2026-02-30) — fall back to default range
            }
        }
        $fromStr = $from->toDateString();
        $toStr   = $to->toDateString();
        $numDays = (int) $from->diffInDays($to) + 1;

        $todayViews   = DB::table('page_views')->where('date', $today)->count();
        $todayUniques = DB::table('page_views')->where('date', $today)->distinct()->count('vhash');

        // range series, zero-filled
        $rows = DB::table('page_views')
            ->whereBetween('date', [$fromStr, $toStr])
            ->selectRaw('date, COUNT(*) as views, COUNT(DISTINCT vhash) as uniques')
            ->groupBy('date')->orderBy('date')->get()
            ->keyBy('date');

        $days = [];
        for ($i = 0; $i < $numDays; $i++) {
            $dt = $from->copy()->addDays($i);
            $d  = $dt->toDateString();
            $r  = $rows->get($d);
            $days[] = ['date' => $d, 'label' => $dt->format('j M'), 'views' => (int) ($r->views ?? 0), 'uniques' => (int) ($r->uniques ?? 0)];
        }
        $max = max(1, ...array_map(fn ($d) => $d['views'], $days));

        $topPages = DB::table('page_views')->whereBetween('date', [$fromStr, $toStr])
            ->selectRaw('path, COUNT(*) as n')->groupBy('path')->orderByDesc('n')->limit(10)->get();

        $topRefs = DB::table('page_views')->whereBetween('date', [$fromStr, $toStr])->whereNotNull('referrer')
            ->selectRaw('referrer, COUNT(*) as n')->groupBy('referrer')->orderByDesc('n')->limit(10)->get();

        return view('admin.analytics', [
            'todayViews'   => $todayViews,
            'todayUniques' => $todayUniques,
            'totalRange'   => array_sum(array_column($days, 'views')),
            'avgRange'     => round(array_sum(array_column($days, 'views')) / max(1, $numDays), 1),
            'numDays'      => $numDays,
            'fromStr'      => $fromStr,
            'toStr'        => $toStr,
            'days'         => $days,
            'max'          => $max,
            'topPages'     => $topPages,
            'topRefs'      => $topRefs,
        ]);
    }
}
