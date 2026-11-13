<?php

namespace App\Support;

use App\Models\Evidence;
use App\Models\Issue;
use App\Models\Observation;
use App\Models\StatusEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Photos attached to citizen updates (observations) and on-site checks.
 * Admin switch (default OFF) is stored in the settings table.
 */
class UpdatePhotos
{
    private const KEY = 'update_photos';

    public static function enabled(): bool
    {
        try {
            return DB::table('settings')->where('key', self::KEY)->value('value') === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function set(bool $on): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => $on ? '1' : '0', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Approved, not hidden photos of this issue's updates. Public. */
    public static function publicFor(Issue $issue): Collection
    {
        $obsIds = Observation::where('issue_id', $issue->id)->pluck('id');
        $eventIds = StatusEvent::where('issue_id', $issue->id)->pluck('id');

        return Evidence::query()
            ->where('moderation', 'approved')
            ->whereNull('hidden_at')
            ->where(function ($q) use ($obsIds, $eventIds) {
                $q->whereIn('observation_id', $obsIds)
                  ->orWhereIn('status_event_id', $eventIds);
            })
            ->orderByDesc('id')
            ->limit(12)
            ->get();
    }
}