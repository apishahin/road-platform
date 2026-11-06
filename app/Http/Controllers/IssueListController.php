<?php

namespace App\Http\Controllers;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use Illuminate\Http\Request;

class IssueListController extends Controller
{
    /** Allowed progress statuses (whitelist, so URL values can't be abused). */
    private const STATUSES = [
        'reported',
        'acknowledged',
        'planned',
        'work_started',
        'completion_reported',
        'repaired',
    ];

    private const SORTS = ['recent', 'reports', 'oldest'];

    public function index(Request $request)
    {
        $status     = (string) $request->query('status', '');
        $categoryId = (int) $request->query('category', 0);
        $areaId     = (int) $request->query('area', 0);
        $search     = trim((string) $request->query('q', ''));
        $sort       = (string) $request->query('sort', 'recent');

        if (! in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'recent';
        }
        $search = mb_substr($search, 0, 100);

        // Duplicates and rejected issues are not shown in the public list.
        $query = Issue::query()
            ->with('category')
            ->whereNotIn('disposition', ['duplicate', 'rejected'])
            ->whereNotNull('published_at');

        if ($status !== '') {
            $query->where('progress_status', $status);
        }
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($areaId > 0) {
            // choosing a division also includes the districts under it
            $areaIds = [$areaId];
            $level = [$areaId];
            for ($depth = 0; $depth < 8 && $level; $depth++) {
                $level = AdminArea::whereIn('parent_id', $level)->pluck('id')->all();
                $areaIds = array_merge($areaIds, $level);
            }
            $query->whereIn('admin_area_id', $areaIds);
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'like', $like)
                  ->orWhere('road_name', 'like', $like)
                  ->orWhere('landmark', 'like', $like);
            });
        }

        match ($sort) {
            'reports' => $query->orderByDesc('reports_count')->orderByDesc('last_activity_at'),
            'oldest'  => $query->orderBy('first_reported_at'),
            default   => $query->orderByDesc('last_activity_at'),
        };
        $query->orderByDesc('id');

        $issues = $query->paginate(12)->withQueryString();

        $categories = Category::orderBy('id')->get()
            ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name()])
            ->all();

        $isBn = app()->getLocale() === 'bn';
        $byParent = AdminArea::orderBy('id')->get()->groupBy(fn ($a) => $a->parent_id ?? 0);
        $areas = [];
        $walk = function ($parentId, $depth) use (&$walk, &$areas, $byParent, $isBn) {
            foreach ($byParent->get($parentId, collect()) as $a) {
                $bn = $a->name_bn ?? null;
                $en = $a->name_en ?? null;
                $label = $isBn ? ($bn ?? $en) : ($en ?? $bn);
                $areas[] = [
                    'id'    => $a->id,
                    'label' => str_repeat("\u{2014} ", $depth) . ($label ?? ('#' . $a->id)),
                ];
                $walk($a->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return view('issues.index', [
            'issues'     => $issues,
            'categories' => $categories,
            'areas'      => $areas,
            'statuses'   => self::STATUSES,
            'filters'    => [
                'status'   => $status,
                'category' => $categoryId,
                'area'     => $areaId,
                'q'        => $search,
                'sort'     => $sort,
            ],
        ]);
    }
}