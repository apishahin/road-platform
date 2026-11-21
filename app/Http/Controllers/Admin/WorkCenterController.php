<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\AuthorityContact;
use App\Models\Evidence;
use App\Models\Issue;
use App\Models\Observation;
use App\Models\Report;
use App\Models\StatusEvent;
use App\Models\User;
use App\Support\IssuePresenter;
use App\Support\ModerationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One role-aware inbox for moderation, repair checks, update-photo review and
 * issue administration. The old specialist pages remain available as drill-down
 * screens for their more involved tools.
 */
class WorkCenterController extends Controller
{
    private const TABS = ['all', 'moderation', 'verify', 'photos', 'issues'];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->canModerate(), 403);

        $tab = (string) $request->query('tab', 'all');
        if (! in_array($tab, self::TABS, true) || ($tab === 'issues' && ! $user->isModerator())) {
            $tab = 'all';
        }

        $search = trim((string) $request->query('q', ''));
        $search = mb_substr($search, 0, 100);
        $issueState = $request->query('issue_state') === 'unpublished' ? 'unpublished' : 'published';
        $branchIds = ModerationScope::branchIds($user);
        [$selection, $selectedAreaIds] = $this->selectedLocation($request, $branchIds);
        $scopeIds = ModerationScope::areaIds($user);

        $reports = $this->reportsQuery($user, $selectedAreaIds, $search);
        $verify = $this->verifyQuery($scopeIds, $selectedAreaIds, $search);
        $photos = $this->photosQuery($scopeIds, $selectedAreaIds, $search);
        $issues = $user->isModerator()
            ? $this->issuesQuery($selectedAreaIds, $search, $issueState)
            : null;

        $counts = [
            'moderation' => (clone $reports)->count(),
            'verify'     => (clone $verify)->count(),
            'photos'     => (clone $photos)->count(),
            'issues'     => $issues ? (clone $issues)->count() : 0,
        ];
        $counts['all'] = $counts['moderation'] + $counts['verify'] + $counts['photos'];

        $cards = match ($tab) {
            'moderation' => $this->moderationCards($reports->limit(30)->get()),
            'verify' => $this->verifyCards($verify->limit(30)->get()),
            'photos' => $this->photoCards($photos->limit(30)->get()),
            'issues' => $this->issueCards(($issues ?: Issue::query()->whereRaw('1 = 0'))->limit(30)->get(), $issueState),
            default => $this->allCards($reports, $verify, $photos),
        };

        $viewData = [
            'tab' => $tab,
            'cards' => $cards,
            'counts' => $counts,
            'issueState' => $issueState,
            'canManageIssues' => $user->isModerator(),
        ];

        if ($request->expectsJson() || $request->header('X-Work-Center') === '1') {
            return response()->json([
                'html' => view('admin.work-center._results', $viewData)->render(),
                'counts' => $counts,
                'title' => __('workcenter.tab_' . $tab),
            ]);
        }

        return view('admin.work-center.index', $viewData + [
            'selection' => $selection,
            'divisions' => $this->divisions($branchIds),
        ]);
    }

    /** @return Builder<Report> */
    private function reportsQuery(User $user, ?array $selectedAreaIds, string $search): Builder
    {
        $query = Report::query()
            ->with([
                'issue.category',
                'issue.adminArea.parent.parent.parent',
                'suggestedArea.parent.parent.parent',
            ])
            ->withCount('evidence')
            ->where('moderation', 'pending')
            ->tap(fn (Builder $q) => ModerationScope::restrict($q, $user));

        if ($selectedAreaIds !== null) {
            $this->applyReportArea($query, $selectedAreaIds);
        }

        if ($search !== '') {
            $this->applyReportSearch($query, $search);
        }

        return $query->orderBy('created_at')->orderBy('id');
    }

    /** @return Builder<Issue> */
    private function verifyQuery(?array $scopeIds, ?array $selectedAreaIds, string $search): Builder
    {
        $query = Issue::query()
            ->active()
            ->where('progress_status', '!=', 'repaired')
            ->whereHas('observations', fn (Builder $q) => $q->where('type', 'fixed'))
            ->withCount(['observations as fixed_count' => fn (Builder $q) => $q->where('type', 'fixed')])
            ->with(['category', 'adminArea.parent.parent.parent']);

        $this->applyIssueScope($query, $scopeIds, $selectedAreaIds, $search);

        return $query->orderByDesc('fixed_count')->orderBy('last_activity_at')->orderBy('id');
    }

    /** @return Builder<Evidence> */
    private function photosQuery(?array $scopeIds, ?array $selectedAreaIds, string $search): Builder
    {
        $issueIds = Issue::query()->select('id');
        $this->applyIssueScope($issueIds, $scopeIds, $selectedAreaIds, $search);

        $observationIds = Observation::query()
            ->select('id')
            ->whereIn('issue_id', clone $issueIds);
        $eventIds = StatusEvent::query()
            ->select('id')
            ->whereIn('issue_id', clone $issueIds);

        return Evidence::query()
            ->where('moderation', 'pending')
            ->where(function (Builder $query) use ($observationIds, $eventIds) {
                $query->whereIn('observation_id', $observationIds)
                    ->orWhereIn('status_event_id', $eventIds);
            })
            ->with([
                'observation.issue.category',
                'observation.issue.adminArea.parent.parent.parent',
                'statusEvent.issue.category',
                'statusEvent.issue.adminArea.parent.parent.parent',
            ])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** @return Builder<Issue> */
    private function issuesQuery(?array $selectedAreaIds, string $search, string $state): Builder
    {
        $query = Issue::query()->with(['category', 'adminArea.parent.parent.parent']);
        if ($selectedAreaIds !== null) {
            $query->whereIn('admin_area_id', $selectedAreaIds);
        }
        $this->applyIssueSearch($query, $search);

        if ($state === 'unpublished') {
            return $query->whereNull('published_at')->whereNotNull('unpublished_at')
                ->orderByDesc('unpublished_at')->orderByDesc('id');
        }

        return $query->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id');
    }

    /** @param Builder<Issue> $query */
    private function applyIssueScope(Builder $query, ?array $scopeIds, ?array $selectedAreaIds, string $search): void
    {
        if ($scopeIds !== null) {
            $scopeIds ? $query->whereIn('admin_area_id', $scopeIds) : $query->whereRaw('1 = 0');
        }
        if ($selectedAreaIds !== null) {
            $query->whereIn('admin_area_id', $selectedAreaIds);
        }
        $this->applyIssueSearch($query, $search);
    }

    /** @param Builder<Issue> $query */
    private function applyIssueSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
        $numeric = ctype_digit(ltrim($search, '#')) ? (int) ltrim($search, '#') : null;
        $query->where(function (Builder $where) use ($like, $numeric) {
            $where->where('title', 'like', $like)
                ->orWhere('road_name', 'like', $like)
                ->orWhere('landmark', 'like', $like);
            if ($numeric) {
                $where->orWhere('id', $numeric);
            }
        });
    }

    /** @param Builder<Report> $query */
    private function applyReportArea(Builder $query, array $areaIds): void
    {
        $query->where(function (Builder $where) use ($areaIds) {
            $where->whereHas('issue', fn (Builder $issue) => $issue->whereIn('admin_area_id', $areaIds))
                ->orWhere(function (Builder $suggested) use ($areaIds) {
                    $suggested->whereIn('suggested_area_id', $areaIds)
                        ->whereDoesntHave('issue', fn (Builder $issue) => $issue->whereNotNull('admin_area_id'));
                });
        });
    }

    /** @param Builder<Report> $query */
    private function applyReportSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
        $numeric = ctype_digit(ltrim($search, '#')) ? (int) ltrim($search, '#') : null;
        $query->where(function (Builder $where) use ($like, $numeric) {
            $where->where('description', 'like', $like)
                ->orWhereHas('issue', function (Builder $issue) use ($like, $numeric) {
                    $issue->where('title', 'like', $like)
                        ->orWhere('road_name', 'like', $like)
                        ->orWhere('landmark', 'like', $like);
                    if ($numeric) {
                        $issue->orWhere('id', $numeric);
                    }
                });
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function allCards(Builder $reports, Builder $verify, Builder $photos): Collection
    {
        return $this->moderationCards($reports->limit(12)->get())
            ->concat($this->verifyCards($verify->limit(12)->get()))
            ->concat($this->photoCards($photos->limit(12)->get()))
            ->sortBy('sort_at')
            ->take(30)
            ->values();
    }

    /** @param Collection<int, Report> $rows @return Collection<int, array<string, mixed>> */
    private function moderationCards(Collection $rows): Collection
    {
        return $rows->map(function (Report $report) {
            $issue = $report->issue;
            $area = $issue?->adminArea ?: $report->suggestedArea;

            return [
                'type' => 'moderation',
                'record' => $report,
                'issue' => $issue,
                'title' => $issue ? $issue->title : __('workcenter.unknown_issue'),
                'issue_no' => $issue?->id,
                'area' => $this->areaLabel($area),
                'meta' => __('workcenter.report_meta', ['photos' => IssuePresenter::num((int) $report->evidence_count)]),
                'sort_at' => $report->created_at ?: now(),
                'when' => $report->created_at?->diffForHumans() ?? '',
                'approve_area_id' => (int) ($issue?->admin_area_id ?: $report->suggested_area_id),
                'detail_url' => route('admin.moderation.index') . '#report-' . $report->id,
            ];
        });
    }

    /** @param Collection<int, Issue> $rows @return Collection<int, array<string, mixed>> */
    private function verifyCards(Collection $rows): Collection
    {
        return $rows->map(function (Issue $issue) {
            return [
                'type' => 'verify',
                'record' => $issue,
                'issue' => $issue,
                'title' => $issue->title,
                'issue_no' => $issue->id,
                'area' => $this->areaLabel($issue->adminArea),
                'meta' => __('workcenter.fixed_claims', ['count' => IssuePresenter::num((int) $issue->fixed_count)]),
                'sort_at' => $issue->last_activity_at ?: $issue->created_at ?: now(),
                'when' => ($issue->last_activity_at ?: $issue->created_at)?->diffForHumans() ?? '',
                'detail_url' => IssuePresenter::url($issue) . '#verify',
            ];
        });
    }

    /** @param Collection<int, Evidence> $rows @return Collection<int, array<string, mixed>> */
    private function photoCards(Collection $rows): Collection
    {
        return $rows->map(function (Evidence $evidence) {
            $observation = $evidence->observation;
            $event = $evidence->statusEvent;
            $issue = $observation?->issue ?: $event?->issue;

            return [
                'type' => 'photos',
                'record' => $evidence,
                'issue' => $issue,
                'title' => $issue?->title ?: __('workcenter.unknown_issue'),
                'issue_no' => $issue?->id,
                'area' => $this->areaLabel($issue?->adminArea),
                'meta' => $observation
                    ? __('workcenter.photo_from_citizen', ['type' => __('issues.obs_' . $observation->type)])
                    : __('workcenter.photo_from_visit'),
                'sort_at' => $evidence->created_at ?: now(),
                'when' => $evidence->created_at?->diffForHumans() ?? '',
                'image_url' => route('photoreview.image', $evidence),
                'detail_url' => route('photoreview.index') . '#photo-' . $evidence->id,
            ];
        })->filter(fn (array $card) => $card['issue'] !== null)->values();
    }

    /** @param Collection<int, Issue> $rows @return Collection<int, array<string, mixed>> */
    private function issueCards(Collection $rows, string $state): Collection
    {
        return $rows->map(function (Issue $issue) use ($state) {
            $at = $state === 'unpublished' ? $issue->unpublished_at : $issue->published_at;

            return [
                'type' => 'issues',
                'record' => $issue,
                'issue' => $issue,
                'title' => $issue->title,
                'issue_no' => $issue->id,
                'area' => $this->areaLabel($issue->adminArea),
                'meta' => __('workcenter.issue_meta', ['reports' => IssuePresenter::num((int) $issue->reports_count)]),
                'sort_at' => $at ?: $issue->created_at ?: now(),
                'when' => $at?->diffForHumans() ?? '',
                'detail_url' => $issue->published_at ? IssuePresenter::url($issue) : null,
            ];
        });
    }

    /** @return array{0: array<string, int>, 1: ?array<int, int>} */
    private function selectedLocation(Request $request, ?array $branchIds): array
    {
        $levels = ['division', 'district', 'upazila', 'union'];
        $selection = [];
        $parentId = null;
        $area = null;

        foreach ($levels as $level) {
            $id = (int) $request->query($level, 0);
            if ($id < 1 || ($level !== 'division' && $parentId === null)) {
                break;
            }

            $query = AdminArea::query()->whereKey($id)->where('level', $level);
            if ($parentId !== null) {
                $query->where('parent_id', $parentId);
            }
            if ($branchIds !== null) {
                $query->whereIn('id', $branchIds);
            }
            $candidate = $query->first();
            if (! $candidate) {
                break;
            }

            $selection[$level] = $candidate->id;
            $area = $candidate;
            $parentId = $candidate->id;
        }

        return [$selection, $area ? $this->descendantIds($area->id) : null];
    }

    /** @return array<int, int> */
    private function descendantIds(int $id): array
    {
        $ids = [$id];
        $level = [$id];
        for ($i = 0; $i < 6 && $level; $i++) {
            $level = AdminArea::whereIn('parent_id', $level)->pluck('id')->map(fn ($v) => (int) $v)->all();
            $ids = array_merge($ids, $level);
        }

        return array_values(array_unique($ids));
    }

    /** @return Collection<int, AdminArea> */
    private function divisions(?array $branchIds): Collection
    {
        $query = AdminArea::query()->where('level', 'division')->orderBy('name_en')->orderBy('name_bn');
        if ($branchIds !== null) {
            $query->whereIn('id', $branchIds);
        }

        return $query->get();
    }

    private function areaLabel(?AdminArea $area): string
    {
        return $area ? AuthorityContact::pathOf($area) : __('workcenter.area_unknown');
    }
}
