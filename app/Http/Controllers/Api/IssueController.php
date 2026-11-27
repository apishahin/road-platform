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
            // A provisional subcategory makes the citizen duplicate decision
            // meaningful: a nearby pothole is not automatically the same as
            // a nearby blocked drain.
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
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
            ->when(isset($v['category_id']), fn ($query) => $query->where('category_id', (int) $v['category_id']))
            ->limit(30)
            ->get();

        \App\Support\IssueNotice::mark($issues);

        return response()->json([
            'data' => $issues->map(fn ($i) => IssuePresenter::map($i))->values(),
        ]);
    }
}