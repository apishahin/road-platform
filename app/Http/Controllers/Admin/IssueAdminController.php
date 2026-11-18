<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Services\IssuePublishService;
use Illuminate\Http\Request;

class IssueAdminController extends Controller
{
    public function __construct(private IssuePublishService $service)
    {
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isModerator(), 403);
    }

    private function divisionList(): array
    {
        $isBn = app()->getLocale() === 'bn';

        return \App\Models\AdminArea::where('level', 'division')->orderBy('name_en')->get()
            ->map(fn ($a) => ['id' => $a->id, 'label' => $isBn ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn)])
            ->values()->all();
    }

    private function areaTreeIds(\App\Models\AdminArea $area): array
    {
        $ids = [$area->id];
        $level = [$area->id];
        for ($i = 0; $i < 6 && $level; $i++) {
            $level = \App\Models\AdminArea::whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    public function index(Request $request)
    {
        $this->guard($request);

        $tab = $request->query('tab') === 'unpublished' ? 'unpublished' : 'published';
        $q = Issue::query();

        $areaId = (int) $request->query('area', 0);
        $noArea = $request->boolean('noarea');
        $areaLabel = null;
        $chain = [];
        if ($noArea) {
            $areaId = 0;
            $q->whereNull('admin_area_id');
        } elseif ($areaId > 0) {
            $areaModel = \App\Models\AdminArea::with('parent.parent.parent')->find($areaId);
            if ($areaModel) {
                $q->whereIn('admin_area_id', $this->areaTreeIds($areaModel));
                $areaLabel = \App\Models\AuthorityContact::pathOf($areaModel);
                $p = $areaModel; $g = 0; while ($p && $g++ < 8) { array_unshift($chain, $p->id); $p = $p->parent; }
            } else {
                $areaId = 0;
            }
        }
        if ($tab === 'published') {
            $q->whereNotNull('published_at')->orderByDesc('published_at');
        } else {
            $q->whereNull('published_at')->whereNotNull('unpublished_at')->orderByDesc('unpublished_at');
        }

        return view('admin.issues.index', [
            'issues' => $q->paginate(20)->withQueryString(),
            'tab' => $tab, 'areaId' => $areaId, 'areaLabel' => $areaLabel, 'noArea' => $noArea, 'divisions' => $this->divisionList(), 'chain' => $chain,
        ]);
    }

    /** CSV download of the current filtered list (UTF-8 BOM so Excel reads Bangla). */
    public function export(Request $request)
    {
        $this->guard($request);

        $tab = $request->query('tab') === 'unpublished' ? 'unpublished' : 'published';
        $q = Issue::query()->with(['category', 'adminArea.parent']);

        $areaId = (int) $request->query('area', 0);
        if ($request->boolean('noarea')) {
            $q->whereNull('admin_area_id');
        } elseif ($areaId > 0) {
            $areaModel = \App\Models\AdminArea::with('parent.parent.parent')->find($areaId);
            if ($areaModel) {
                $q->whereIn('admin_area_id', $this->areaTreeIds($areaModel));
            }
        }
        if ($tab === 'published') {
            $q->whereNotNull('published_at')->orderByDesc('published_at');
        } else {
            $q->whereNull('published_at')->whereNotNull('unpublished_at')->orderByDesc('unpublished_at');
        }

        $filename = 'issues-'.$tab.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'id', 'public_id', 'title', 'category_bn', 'category_en',
                'area', 'parent_area', 'status', 'verification', 'reports_count',
                'lat', 'lng', 'published_at', 'created_at', 'url',
            ]);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $i) {
                    fputcsv($out, [
                        $i->id,
                        $i->public_id,
                        $i->title,
                        $i->category?->name_bn,
                        $i->category?->name_en,
                        $i->adminArea?->name_bn,
                        $i->adminArea?->parent?->name_bn,
                        $i->progress_status,
                        $i->verification_status,
                        $i->reports_count,
                        $i->lat,
                        $i->lng,
                        $i->published_at,
                        $i->created_at,
                        route('issues.show', $i->public_id),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function unpublish(Request $request, Issue $issue)
    {
        $this->guard($request);
        $data = $request->validate(['reason' => ['required', 'in:privacy,wrong,duplicate,other']]);
        $ok = $this->service->unpublish($issue, $request->user(), $data['reason']);

        return back()->with('status', __($ok ? 'issueadmin.unpublished_msg' : 'issueadmin.not_allowed'));
    }

    public function republish(Request $request, Issue $issue)
    {
        $this->guard($request);
        $ok = $this->service->republish($issue, $request->user());

        return back()->with('status', __($ok ? 'issueadmin.republished_msg' : 'issueadmin.not_allowed'));
    }

    public function edit(Request $request, Issue $issue)
    {
        $this->guard($request);

        return view('admin.issues.edit', ['issue' => $issue]);
    }

    public function update(Request $request, Issue $issue)
    {
        $this->guard($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'road_name' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ]);
        $changed = $this->service->update($issue, $request->user(), $data);

        return redirect()->route('admin.issues.index')
            ->with('status', __($changed ? 'issueadmin.saved_msg' : 'issueadmin.nochange_msg'));
    }
}