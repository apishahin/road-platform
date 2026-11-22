<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Support\BangladeshMap;
use App\Support\IssuePresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IssueController extends Controller
{
    /** GET /api/v1/issues/nearby?lat=..&lng=..&radius=.. */
    public function nearby(Request $request)
    {
        $v = $request->validate([
            'lat'    => ['required', 'numeric', 'between:-90,90'],
            'lng'    => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:50', 'max:5000'],
        ]);

        if (! BangladeshMap::contains((float) $v['lat'], (float) $v['lng'])) {
            throw ValidationException::withMessages([
                'lat' => __('report.outside_bangladesh'),
            ]);
        }

        $issues = Issue::query()
            ->active()
            ->with('category')
            ->nearby((float) $v['lat'], (float) $v['lng'], (int) ($v['radius'] ?? 1000))
            ->limit(30)
            ->get();

        \App\Support\IssueNotice::mark($issues);

        return response()->json([
            'data' => $issues->map(fn ($i) => IssuePresenter::map($i))->values(),
        ]);
    }
}