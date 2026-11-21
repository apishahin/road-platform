<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use App\Models\User;
use App\Support\IssuePresenter;
use Illuminate\Http\Request;

/**
 * Public read-only API v1 — for the mobile app, researchers and third-party
 * sites. No write endpoints; auth is handled by the ApiAccess middleware.
 */
class PublicApiController extends Controller
{
    /** GET /api/v1/issues?category=&area_id=&status=&verified=&page= */
    public function issues(Request $request)
    {
        $v = $request->validate([
            'category' => ['nullable', 'string', 'max:60'],
            'area_id'  => ['nullable', 'integer'],
            'status'   => ['nullable', 'in:reported,acknowledged,planned,work_started,completion_reported,repaired'],
            'verified' => ['nullable', 'in:0,1'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        $q = Issue::query()->active()->with(['category', 'adminArea'])
            ->orderByDesc('last_activity_at');

        if (! empty($v['category'])) {
            $q->whereHas('category', fn ($c) => $c->where('slug', $v['category']));
        }
        if (! empty($v['area_id'])) {
            $q->where('admin_area_id', (int) $v['area_id']);
        }
        if (! empty($v['status'])) {
            $q->where('progress_status', $v['status']);
        }
        if (isset($v['verified'])) {
            $q->where('verification_status', $v['verified'] === '1' ? 'verified' : 'unverified');
        }

        $p = $q->paginate(25);

        return response()->json([
            'data' => collect($p->items())->map(fn ($i) => $this->issueArray($i))->values(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
            ],
        ]);
    }

    /** GET /api/v1/issues/{public_id} */
    public function show(Issue $issue)
    {
        abort_unless($issue->disposition !== 'removed', 404);

        return response()->json(['data' => $this->issueArray($issue, true)]);
    }

    /** GET /api/v1/categories */
    public function categories()
    {
        $rows = Category::query()->where('is_active', 1)->orderBy('sort')->get()
            ->map(fn ($c) => [
                'id'      => $c->id,
                'slug'    => $c->slug,
                'name_bn' => $c->name_bn,
                'name_en' => $c->name_en,
            ])->values();

        return response()->json(['data' => $rows]);
    }

    /** GET /api/v1/areas?level=division|district|upazila&parent= */
    public function areas(Request $request)
    {
        $v = $request->validate([
            'level'  => ['nullable', 'in:division,district,upazila,union'],
            'parent' => ['nullable', 'integer'],
        ]);

        $q = AdminArea::query()->orderBy('name_en');
        if (! empty($v['level'])) {
            $q->where('level', $v['level']);
        }
        if (! empty($v['parent'])) {
            $q->where('parent_id', (int) $v['parent']);
        }

        return response()->json([
            'data' => $q->limit(500)->get()->map(fn ($a) => [
                'id'      => $a->id,
                'level'   => $a->level,
                'parent'  => $a->parent_id,
                'name_bn' => $a->name_bn,
                'name_en' => $a->name_en,
            ])->values(),
        ]);
    }

    /** GET /api/v1/stats — headline counters for dashboards/widgets. */
    public function stats()
    {
        $base = Issue::query()->active();

        return response()->json(['data' => [
            'issues_total'    => (clone $base)->count(),
            'issues_repaired' => (clone $base)->where('progress_status', 'repaired')->count(),
            'issues_verified' => (clone $base)->where('verification_status', 'verified')->count(),
            'issues_open'     => (clone $base)->whereNotIn('progress_status', ['repaired'])->count(),
            'districts_covered' => Issue::activeDistrictCount(),
            'reporters'       => User::where('role', 'citizen')->count(),
        ]]);
    }

    private function issueArray(Issue $i, bool $full = false): array
    {
        $d = IssuePresenter::display($i);
        $row = [
            'id'           => $i->public_id,
            'title'        => $i->title,
            'lat'          => (float) $i->lat,
            'lng'          => (float) $i->lng,
            'category'     => $i->category?->slug,
            'status'       => [
                'progress'     => $i->progress_status,
                'label'        => $d['label'],
                'color'        => $d['color'],
                'verified'     => $i->verification_status === 'verified',
                'reopened'     => $i->disposition === 'reopened',
            ],
            'area'         => $i->adminArea ? ($i->adminArea->name_en ?: $i->adminArea->name_bn) : null,
            'reports_count' => (int) $i->reports_count,
            'reported_at'  => $i->first_reported_at?->toIso8601String(),
            'updated_at'   => $i->last_activity_at?->toIso8601String(),
            'url'          => IssuePresenter::url($i),
        ];
        if ($full) {
            $row['photos'] = $i->evidence()
                ->where('evidence.moderation', 'approved')
                ->whereNull('hidden_at')
                ->orderByDesc('evidence.created_at')
                ->get()
                ->map(fn ($e) => $e->url())
                ->values();
        }

        return $row;
    }
}
