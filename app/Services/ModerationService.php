<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ModerationService
{
    /** Approve a report: publish its issue (if hidden), count it, publish its photos. */
    public function approve(Report $report, User $moderator, ?int $areaId = null): void
    {
        $copied = [];

        try {
            DB::transaction(function () use ($report, &$copied, $areaId) {
                $r = Report::with(['evidence', 'issue'])->whereKey($report->id)->lockForUpdate()->firstOrFail();
                if ($r->moderation === 'approved') {
                    return;
                }

                $r->moderation = 'approved';
                $r->save();

                foreach ($r->evidence as $ev) {
                    if ($new = $this->publishEvidence($ev)) {
                        $copied[] = $new;
                    }
                }

                $issue = $r->issue;
                $issue->increment('reports_count');

                // Set the area only if the issue has none yet; never overwrite an existing one.
                if ($areaId) {
                    \App\Models\Issue::whereKey($issue->id)->whereNull('admin_area_id')->update(['admin_area_id' => $areaId]);
                }
                $issue->forceFill([
                    'published_at'     => $issue->published_at ?? now(),
                    'last_activity_at' => now(),
                ])->save();
            });
        } catch (\Throwable $e) {
            Storage::disk(\App\Support\StorageSettings::publicDisk())->delete($copied);
            throw $e;
        }
    }

    /** Hide a report and take its photos back to private storage. */
    public function hide(Report $report, User $moderator): void
    {
        DB::transaction(function () use ($report) {
            $r = Report::with(['evidence', 'issue'])->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($r->moderation === 'hidden') {
                return;
            }

            $wasApproved = $r->moderation === 'approved';
            $r->moderation = 'hidden';
            $r->save();

            foreach ($r->evidence as $ev) {
                $this->unpublishEvidence($ev);
            }

            if ($wasApproved && $r->issue->reports_count > 0) {
                $r->issue->decrement('reports_count');
            }
        });
    }

    /** Hide one photo on its own (face, number plate, private info). Returns false if it was already hidden. */
    public function hideEvidence(Evidence $evidence, User $moderator, string $reason): bool
    {
        return DB::transaction(function () use ($evidence, $moderator, $reason) {
            $ev = Evidence::whereKey($evidence->id)->lockForUpdate()->firstOrFail();
            if ($ev->hidden_at) {
                return false;
            }

            $ev->forceFill([
                'hidden_at'     => now(),
                'hidden_by'     => $moderator->id,
                'hidden_reason' => $reason,
            ]);
            $this->unpublishEvidence($ev);

            return true;
        });
    }

    private function publishEvidence(Evidence $ev): ?string
    {
        // A photo hidden on its own (face, number plate...) is never published.
        if ($ev->hidden_at) {
            return null;
        }
        if ($ev->moderation === 'approved') {
            return null;
        }

        $private = Storage::disk($ev->private_disk ?: \App\Support\StorageSettings::privateDisk());
        $public  = Storage::disk(\App\Support\StorageSettings::publicDisk());
        $newPath = 'evidence/public/' . basename($ev->public_path);

        $public->put($newPath, $private->get($ev->public_path));
        $private->delete($ev->public_path);

        $ev->forceFill(['public_path' => $newPath, 'moderation' => 'approved'])->save();

        return $newPath;
    }

    private function unpublishEvidence(Evidence $ev): void
    {
        if ($ev->moderation === 'approved') {
            $private = Storage::disk(\App\Support\StorageSettings::privateDisk());
            $public  = Storage::disk($ev->public_disk ?: \App\Support\StorageSettings::publicDisk());
            $back    = 'evidence/pending/' . basename($ev->public_path);

            if ($public->exists($ev->public_path)) {
                $private->put($back, $public->get($ev->public_path));
                $public->delete($ev->public_path);
                $ev->public_path = $back;
            }
        }

        $ev->moderation = 'hidden';
        $ev->save();
    }
}