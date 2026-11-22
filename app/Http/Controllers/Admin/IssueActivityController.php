<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AdminArea, AuditEvent, Evidence, Issue, IssueNotification, Report, StatusEvent, User};
use App\Support\IssuePresenter;
use App\Support\StatusLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Private, administrator-only operational history for one issue.
 *
 * Public timelines intentionally expose roles rather than people. This screen
 * joins the append-only status log, moderation audit records, and authority
 * notification records without changing that public privacy boundary.
 */
class IssueActivityController extends Controller
{
    private const STAFF_ROLES = ['volunteer', 'moderator', 'admin'];

    public function index(Request $request, Issue $issue)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $statusEvents = StatusEvent::query()
            ->where('issue_id', $issue->id)
            ->with('actor:id,display_name,role')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $reports = Report::query()
            ->where('issue_id', $issue->id)
            ->get(['id', 'public_id']);
        $statusEventIds = $statusEvents->pluck('id')->all();

        $evidenceIds = $reports->isEmpty() && $statusEventIds === []
            ? []
            : Evidence::query()
                ->where(function ($query) use ($reports, $statusEventIds) {
                    if ($reports->isNotEmpty()) {
                        $query->whereIn('report_id', $reports->pluck('id'));
                    }

                    if ($statusEventIds !== []) {
                        $method = $reports->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('status_event_id', $statusEventIds);
                    }
                })
                ->pluck('id')
                ->all();

        $audits = $this->auditsFor($issue, $reports->pluck('public_id')->all(), $evidenceIds);
        $areaLabels = $this->areaLabels($audits);

        $activity = collect()
            ->merge($statusEvents->map(fn (StatusEvent $event) => $this->statusEntry($event)))
            ->merge($audits->map(fn (AuditEvent $event) => $this->auditEntry($event, $areaLabels))->filter())
            ->merge($this->notificationEntries($issue))
            ->merge($this->authorityLinkEntries($issue))
            ->sortBy([
                ['sort_at', 'asc'],
                ['sequence', 'asc'],
            ])->values();

        return view('admin.issues.activity', [
            'issue' => $issue,
            'activity' => $activity,
        ]);
    }

    /** @return Collection<int, AuditEvent> */
    private function auditsFor(Issue $issue, array $reportPublicIds, array $evidenceIds): Collection
    {
        return AuditEvent::query()
            ->with('actor:id,display_name,role')
            ->where(function ($query) use ($issue, $reportPublicIds, $evidenceIds) {
                // Notifications have a richer first-class record below, so do
                // not show the audit shadow as a confusing duplicate.
                $query->where(function ($issueAudit) use ($issue) {
                    $issueAudit->where('target_type', 'issue')
                        ->where('target_id', (string) $issue->id)
                        ->where('action', '!=', 'issue.notify');
                });

                if ($reportPublicIds !== []) {
                    $query->orWhere(function ($reportAudit) use ($reportPublicIds) {
                        $reportAudit->where('target_type', 'report')
                            ->whereIn('target_id', $reportPublicIds);
                    });
                }

                if ($evidenceIds !== []) {
                    $query->orWhere(function ($evidenceAudit) use ($evidenceIds) {
                        $evidenceAudit->where('target_type', 'evidence')
                            ->whereIn('target_id', array_map('strval', $evidenceIds));
                    });
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, string> */
    private function areaLabels(Collection $audits): array
    {
        $ids = $audits->where('action', 'issue.area_set')
            ->map(fn (AuditEvent $event) => (int) (($event->details ?? [])['area_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $isBangla = app()->getLocale() === 'bn';

        return AdminArea::whereIn('id', $ids)->get()->mapWithKeys(function (AdminArea $area) use ($isBangla) {
            return [$area->id => $isBangla ? ($area->name_bn ?: $area->name_en) : ($area->name_en ?: $area->name_bn)];
        })->all();
    }

    /** @return array<string, mixed> */
    private function statusEntry(StatusEvent $event): array
    {
        $context = [];
        if ($event->from_value !== null && $event->from_value !== $event->to_value) {
            $context[] = __('issueactivity.transition', [
                'from' => $this->statusValue($event->dimension, $event->from_value),
                'to' => $this->statusValue($event->dimension, $event->to_value),
            ]);
        }

        $reason = StatusLabel::reason($event->reason);
        if ($reason !== '') {
            $context[] = __('issueactivity.note', ['note' => $reason]);
        }

        if ($event->source_type !== '') {
            $context[] = __('issueactivity.source', ['source' => StatusLabel::source($event->source_type)]);
        }

        return $this->entry(
            $event->created_at,
            (int) $event->id,
            $this->actor($event->actor, $event->actor_type),
            __('issueactivity.status_changed', ['status' => StatusLabel::forEvent($event)]),
            $context,
        );
    }

    /** @return array<string, mixed>|null */
    private function auditEntry(AuditEvent $event, array $areaLabels): ?array
    {
        $details = is_array($event->details) ? $event->details : [];
        $context = [];

        $action = match ($event->action) {
            'issue.unpublish' => __('issueactivity.issue_unpublished'),
            'issue.republish' => __('issueactivity.issue_republished'),
            'issue.edit' => __('issueactivity.issue_edited'),
            'issue.area_set' => __('issueactivity.issue_area_set'),
            'report.approved' => __('issueactivity.report_approved'),
            'report.hidden' => __('issueactivity.report_hidden'),
            'evidence.publish' => __('issueactivity.photo_approved'),
            'evidence.hide' => __('issueactivity.photo_hidden'),
            'evidence.blur' => __('issueactivity.photo_redacted'),
            default => null,
        };

        if ($action === null) {
            return null;
        }

        if ($event->action === 'issue.unpublish' && isset($details['reason'])) {
            $context[] = __('issueactivity.reason', [
                'reason' => __('issueadmin.reason_' . $details['reason']),
            ]);
        }

        if ($event->action === 'issue.edit') {
            foreach (['title', 'road_name', 'landmark'] as $field) {
                if (! is_array($details[$field] ?? null)) {
                    continue;
                }

                $from = trim((string) ($details[$field]['from'] ?? ''));
                $to = trim((string) ($details[$field]['to'] ?? ''));
                $context[] = __('issueactivity.field_change', [
                    'field' => __('issueactivity.field_' . $field),
                    'from' => $from !== '' ? $from : __('issueactivity.empty'),
                    'to' => $to !== '' ? $to : __('issueactivity.empty'),
                ]);
            }
        }

        if ($event->action === 'issue.area_set') {
            $areaId = (int) ($details['area_id'] ?? 0);
            if ($areaId && isset($areaLabels[$areaId])) {
                $context[] = __('issueactivity.area', ['area' => $areaLabels[$areaId]]);
            }
        }

        if (in_array($event->action, ['report.approved', 'report.hidden'], true)
            && isset($details['from'], $details['to'])) {
            $context[] = __('issueactivity.moderation_transition', [
                'from' => (string) $details['from'],
                'to' => (string) $details['to'],
            ]);
        }

        if ($event->action === 'evidence.hide' && isset($details['reason'])) {
            $context[] = __('issueactivity.reason', [
                'reason' => __('issues.photo_reason_' . $details['reason']),
            ]);
        }

        if ($event->action === 'evidence.blur') {
            $boxes = (int) ($details['boxes'] ?? 0);
            if ($boxes > 0) {
                $context[] = __('issueactivity.redaction_boxes', ['count' => IssuePresenter::num($boxes)]);
            }
        }

        return $this->entry(
            $event->created_at,
            100000000 + (int) $event->id,
            $this->actor($event->actor),
            $action,
            $context,
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    private function notificationEntries(Issue $issue): Collection
    {
        return IssueNotification::query()
            ->where('issue_id', $issue->id)
            ->with(['sender:id,display_name,role', 'contact.area.parent.parent.parent'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (IssueNotification $notification) {
                $contact = $notification->contact;
                $context = [];

                if ($contact) {
                    $contactName = trim((string) $contact->name);
                    $position = trim((string) $contact->position);
                    $who = implode(' — ', array_filter([$contactName, $position]));
                    if ($who !== '') {
                        $context[] = __('issueactivity.authority', ['authority' => $who]);
                    }

                    $area = \App\Models\AuthorityContact::pathOf($contact->area);
                    if ($area !== '') {
                        $context[] = __('issueactivity.area', ['area' => $area]);
                    }
                }

                $message = Str::squish((string) $notification->message);
                if ($message !== '') {
                    $context[] = __('issueactivity.message', ['message' => Str::limit($message, 500)]);
                }

                if ($notification->error) {
                    $context[] = __('issueactivity.delivery_error', ['error' => $notification->error]);
                }

                $channel = __('notify.ch_' . $notification->channel);
                $status = __('notify.st_' . $notification->status);

                return $this->entry(
                    $notification->created_at,
                    200000000 + (int) $notification->id,
                    $this->actor($notification->sender),
                    __('issueactivity.authority_notification', ['channel' => $channel, 'status' => $status]),
                    $context,
                );
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function authorityLinkEntries(Issue $issue): Collection
    {
        return $issue->authorityLinks()
            ->with(['authority', 'linkedByUser:id,display_name,role'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function ($link) {
                $authority = $link->authority;
                $name = $authority
                    ? (app()->getLocale() === 'bn' ? ($authority->name_bn ?: $authority->name_en) : ($authority->name_en ?: $authority->name_bn))
                    : '';
                $context = [];

                if ($name !== '') {
                    $context[] = __('issueactivity.authority', ['authority' => $name]);
                }
                if (trim((string) $link->basis) !== '') {
                    $context[] = __('issueactivity.basis', ['basis' => Str::limit(Str::squish($link->basis), 500)]);
                }

                return $this->entry(
                    $link->created_at,
                    300000000 + (int) $link->id,
                    $this->actor($link->linkedByUser),
                    __('issueactivity.authority_linked'),
                    $context,
                );
            });
    }

    /** @return array{name: ?string, role: string} */
    private function actor(?User $actor, ?string $storedRole = null): array
    {
        $role = $actor?->role ?: ($storedRole ?: 'system');
        $staff = $actor && in_array($actor->role, self::STAFF_ROLES, true);
        $name = $staff ? trim((string) $actor->display_name) : '';

        return [
            'name' => $name !== '' ? $name : null,
            'role' => $role === 'system' ? __('issueactivity.system') : __('adminusers.role_' . $role),
        ];
    }

    /** @return array<string, mixed> */
    private function entry(?\DateTimeInterface $at, int $sequence, array $actor, string $action, array $context): array
    {
        return [
            'sort_at' => $at ? $at->getTimestamp() : 0,
            'sequence' => $sequence,
            'when' => $this->formatTime($at),
            'actor' => $actor,
            'action' => $action,
            'context' => array_values(array_filter($context, fn ($line) => $line !== '')),
        ];
    }

    private function statusValue(string $dimension, ?string $value): string
    {
        if ($value === null || $value === '') {
            return __('issueactivity.empty');
        }

        return match ($dimension) {
            'progress' => __('issue.progress.' . $value),
            'verification' => __('issue.verification.' . $value),
            'disposition' => __('issue.disposition.' . $value),
            default => $value,
        };
    }

    private function formatTime(?\DateTimeInterface $time): string
    {
        return $time ? IssuePresenter::num($time->format('d/m/Y, h:i A')) : '—';
    }
}
