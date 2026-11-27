<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Services\IssuePublishService;
use App\Support\BangladeshMap;
use App\Support\IssuePresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IssueAdminController extends Controller
{
    public function __construct(private IssuePublishService $service) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isModerator(), 403);
    }

    private function divisionList(): array
    {
        $isBn = app()->getLocale() === 'bn';
        return \App\Models\AdminArea::where('level', 'division')->orderBy('name_en')->get()
            ->map(fn ($area) => [
                'id' => $area->id,
                'label' => $isBn ? ($area->name_bn ?: $area->name_en) : ($area->name_en ?: $area->name_bn),
            ])->values()->all();
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

    private function tab(Request $request): string
    {
        return in_array($request->query('tab'), ['unpublished', 'location_review'], true)
            ? $request->query('tab') : 'published';
    }

    /** Apply the shared admin-area filter and return view state. */
    private function applyLocationFilter($query, Request $request): array
    {
        $areaId = (int) $request->query('area', 0);
        $noArea = $request->boolean('noarea');
        $areaLabel = null;
        $chain = [];

        if ($noArea) {
            $areaId = 0;
            $query->whereNull('admin_area_id');
        } elseif ($areaId > 0) {
            $area = \App\Models\AdminArea::with('parent.parent.parent')->find($areaId);
            if ($area) {
                $query->whereIn('admin_area_id', $this->areaTreeIds($area));
                $areaLabel = \App\Models\AuthorityContact::pathOf($area);
                $parent = $area;
                for ($i = 0; $parent && $i < 8; $i++) {
                    array_unshift($chain, $parent->id);
                    $parent = $parent->parent;
                }
            } else {
                $areaId = 0;
            }
        }

        return [$areaId, $noArea, $areaLabel, $chain];
    }

    private function applyState($query, string $tab): void
    {
        if ($tab === 'location_review') {
            $query->where('location_review_status', Issue::LOCATION_REVIEW)
                ->whereNull('published_at')->orderBy('created_at');
        } elseif ($tab === 'unpublished') {
            $query->whereNull('published_at')->whereNotNull('unpublished_at')->orderByDesc('unpublished_at');
        } else {
            $query->whereNotNull('published_at')->orderByDesc('published_at');
        }
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $tab = $this->tab($request);
        $query = Issue::query();
        [$areaId, $noArea, $areaLabel, $chain] = $this->applyLocationFilter($query, $request);
        $this->applyState($query, $tab);

        return view('admin.issues.index', [
            'issues' => $query->paginate(20)->withQueryString(),
            'tab' => $tab,
            'areaId' => $areaId,
            'areaLabel' => $areaLabel,
            'noArea' => $noArea,
            'divisions' => $this->divisionList(),
            'chain' => $chain,
        ]);
    }

    public function export(Request $request)
    {
        $this->guard($request);
        $tab = $this->tab($request);
        $query = Issue::query()->with(['category', 'adminArea.parent']);
        $this->applyLocationFilter($query, $request);
        $this->applyState($query, $tab);
        $filename = 'issues-' . $tab . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'public_id', 'title', 'category_bn', 'category_en', 'area', 'parent_area', 'status', 'verification', 'location_review_status', 'place_name', 'lat', 'lng', 'published_at', 'created_at', 'url']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $issue) {
                    fputcsv($out, [
                        $issue->id, $issue->public_id, $issue->title,
                        $issue->category?->name_bn, $issue->category?->name_en,
                        $issue->adminArea?->name_bn, $issue->adminArea?->parent?->name_bn,
                        $issue->progress_status, $issue->verification_status, $issue->location_review_status,
                        $issue->place_name, $issue->lat, $issue->lng, $issue->published_at,
                        $issue->created_at, IssuePresenter::url($issue),
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
            'place_name' => ['nullable', 'string', 'max:200'],
            'location_note' => ['nullable', 'string', 'max:500'],
            'location_precision' => ['required', 'in:exact,approximate,text_only'],
            'lat' => ['nullable', 'numeric', 'between:20.60,26.65'],
            'lng' => ['nullable', 'numeric', 'between:88.00,92.72'],
        ]);

        if ($data['location_precision'] === 'text_only') {
            if (trim((string) ($data['place_name'] ?? '')) === '') {
                throw ValidationException::withMessages(['place_name' => __('report.place_required')]);
            }
            $data['lat'] = null;
            $data['lng'] = null;
            $data['location_review_status'] = Issue::LOCATION_REVIEW;
        } else {
            if (! isset($data['lat'], $data['lng'])) {
                throw ValidationException::withMessages(['lat' => __('report.pick_point')]);
            }
            if (! BangladeshMap::contains((float) $data['lat'], (float) $data['lng'])) {
                throw ValidationException::withMessages(['lat' => __('report.outside_bangladesh')]);
            }
            $data['location_review_status'] = Issue::LOCATION_READY;
        }

        $changed = $this->service->update($issue, $request->user(), $data);
        return redirect()->route('admin.issues.index', [
            'tab' => $data['location_review_status'] === Issue::LOCATION_REVIEW ? 'location_review' : 'published',
        ])->with('status', __($changed ? 'issueadmin.saved_msg' : 'issueadmin.nochange_msg'));
    }
}
