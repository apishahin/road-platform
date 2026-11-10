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