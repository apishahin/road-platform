<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssuePublishService
{
    public function __construct(
        private ModerationService $moderation,
        private AuditService $audit,
    ) {
    }

    public function unpublish(Issue $issue, User $actor, string $reason): bool
    {
        return DB::transaction(function () use ($issue, $actor, $reason) {
            $i = Issue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if ($i->published_at === null) {
                return false;
            }
            $was = (string) $i->published_at;

            $i->forceFill([
                'published_at' => null,
                'unpublished_at' => now(),
                'unpublished_by' => $actor->id,
                'unpublish_reason' => $reason,
            ])->save();

            $reports = Report::with('evidence')
                ->where('issue_id', $i->id)->where('moderation', 'approved')->get();
            foreach ($reports as $r) {
                foreach ($r->evidence as $ev) {
                    if ($ev->moderation === 'approved') {
                        $this->moderation->unpublishEvidence($ev);
                    }
                }
            }

            $this->audit->log($actor, 'issue.unpublish', 'issue', (string) $i->id, [
                'reason' => $reason,
                'was_published_at' => $was,
            ]);

            return true;
        });
    }

    public function republish(Issue $issue, User $actor): bool
    {
        return DB::transaction(function () use ($issue, $actor) {
            $i = Issue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if ($i->published_at !== null || $i->unpublished_at === null) {
                return false;
            }

            $i->forceFill([
                'published_at' => now(),
                'unpublished_at' => null,
                'unpublished_by' => null,
                'unpublish_reason' => null,
            ])->save();

            $reports = Report::with('evidence')
                ->where('issue_id', $i->id)->where('moderation', 'approved')->get();
            foreach ($reports as $r) {
                foreach ($r->evidence as $ev) {
                    if ($ev->hidden_at === null && $ev->moderation === 'hidden') {
                        $this->moderation->publishEvidence($ev);
                    }
                }
            }

            $this->audit->log($actor, 'issue.republish', 'issue', (string) $i->id, null);

            return true;
        });
    }

    public function update(Issue $issue, User $actor, array $data): bool
    {
        $changes = [];
        foreach (['title', 'road_name', 'landmark'] as $k) {
            $new = isset($data[$k]) ? trim((string) $data[$k]) : null;
            if ($new === '') {
                $new = null;
            }
            if ($issue->{$k} !== $new) {
                $changes[$k] = ['from' => $issue->{$k}, 'to' => $new];
            }
        }
        if (! $changes) {
            return false;
        }

        $issue->forceFill(array_map(fn ($c) => $c['to'], $changes))->save();
        $this->audit->log($actor, 'issue.edit', 'issue', (string) $issue->id, $changes);

        return true;
    }
}