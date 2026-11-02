<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Report;
use App\Support\IssuePresenter;

class HomeController extends Controller
{
    public function index()
    {
        // Latest active issues, newest activity first
        $issues = Issue::query()
            ->active()
            ->with('category')
            ->latest('last_activity_at')
            ->limit(config('roadplatform.map.max_markers'))
            ->get();

        // Numbers shown in the hero section
        $stats = [
            'open'     => Issue::active()->where('progress_status', '!=', 'repaired')->count(),
            'repaired' => Issue::active()->where('progress_status', 'repaired')->count(),
            'reports'  => Report::count(),
        ];

        return view('home', [
            'recent'    => $issues->take(6),
            'mapIssues' => $issues->map(fn ($i) => IssuePresenter::map($i))->values(),
            'stats'     => $stats,
        ]);
    }
}