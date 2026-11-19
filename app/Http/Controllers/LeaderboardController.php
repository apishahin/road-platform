<?php

namespace App\Http\Controllers;

use App\Models\{Observation, Report, User};

class LeaderboardController extends Controller
{
    /**
     * Public citizen leaderboard — computed from existing approved activity,
     * no extra storage. Score = approved reports ×3 + on-site observations ×1.
     */
    public function index()
    {
        $reportCounts = Report::where('moderation', 'approved')
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as c')->pluck('c', 'user_id');
        $obsCounts = Observation::groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as c')->pluck('c', 'user_id');

        $scores = [];
        foreach ($reportCounts as $uid => $c) {
            $scores[$uid] = ($scores[$uid] ?? 0) + 3 * (int) $c;
        }
        foreach ($obsCounts as $uid => $c) {
            $scores[$uid] = ($scores[$uid] ?? 0) + (int) $c;
        }
        arsort($scores);

        $topIds = array_slice(array_keys($scores), 0, 30, true);
        $users  = User::whereIn('id', $topIds)->get()->keyBy('id');

        $rows = [];
        $rank = 0;
        foreach ($topIds as $uid) {
            $u = $users[$uid] ?? null;
            if (! $u) {
                continue;
            }
            $rank++;
            $rows[] = [
                'rank'    => $rank,
                'name'    => trim((string) $u->display_name) !== '' ? $u->display_name : __('issues.lb_anon'),
                'score'   => $scores[$uid],
                'reports' => (int) ($reportCounts[$uid] ?? 0),
                'obs'     => (int) ($obsCounts[$uid] ?? 0),
                'badge'   => self::badge($scores[$uid]),
                'me'      => auth()->check() && auth()->id() === (int) $uid,
            ];
        }

        // Current user's own standing (even if outside top 30)
        $me = null;
        if (auth()->check()) {
            $uid  = auth()->id();
            $sc   = $scores[$uid] ?? 0;
            $pos  = 0;
            foreach ($scores as $id => $s) {
                if ($s > $sc || ($s === $sc && $id < $uid)) {
                    $pos++;
                }
            }
            $me = [
                'score'   => $sc,
                'rank'    => $sc > 0 ? $pos + 1 : null,
                'reports' => (int) ($reportCounts[$uid] ?? 0),
                'obs'     => (int) ($obsCounts[$uid] ?? 0),
                'badge'   => self::badge($sc),
            ];
        }

        return view('leaderboard', ['rows' => $rows, 'me' => $me]);
    }

    /** Badge tier for a score. */
    public static function badge(int $score): string
    {
        return match (true) {
            $score >= 50 => __('issues.lb_badge_hero'),
            $score >= 15 => __('issues.lb_badge_star'),
            $score >= 5  => __('issues.lb_badge_active'),
            $score >= 1  => __('issues.lb_badge_helper'),
            default      => __('issues.lb_badge_new'),
        };
    }
}
