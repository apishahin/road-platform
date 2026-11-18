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
        $from  = now()->subDays(29)->toDateString();

        $todayViews   = DB::table('page_views')->where('date', $today)->count();
        $todayUniques = DB::table('page_views')->where('date', $today)->distinct()->count('vhash');

        // 30-day series, zero-filled
        $rows = DB::table('page_views')
            ->where('date', '>=', $from)
            ->selectRaw('date, COUNT(*) as views, COUNT(DISTINCT vhash) as uniques')
            ->groupBy('date')->orderBy('date')->get()
            ->keyBy('date');

        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $r = $rows->get($d);
            $days[] = ['date' => $d, 'label' => now()->subDays($i)->format('j M'), 'views' => (int) ($r->views ?? 0), 'uniques' => (int) ($r->uniques ?? 0)];
        }
        $max = max(1, ...array_map(fn ($d) => $d['views'], $days));

        $topPages = DB::table('page_views')->where('date', '>=', $from)
            ->selectRaw('path, COUNT(*) as n')->groupBy('path')->orderByDesc('n')->limit(10)->get();

        $topRefs = DB::table('page_views')->where('date', '>=', $from)->whereNotNull('referrer')
            ->selectRaw('referrer, COUNT(*) as n')->groupBy('referrer')->orderByDesc('n')->limit(10)->get();

        return view('admin.analytics', [
            'todayViews'   => $todayViews,
            'todayUniques' => $todayUniques,
            'total30'      => array_sum(array_column($days, 'views')),
            'avg30'        => round(array_sum(array_column($days, 'views')) / 30, 1),
            'days'         => $days,
            'max'          => $max,
            'topPages'     => $topPages,
            'topRefs'      => $topRefs,
        ]);
    }
}
