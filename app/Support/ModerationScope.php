<?php

namespace App\Support;

use App\Models\AdminArea;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which reports may this moderator see and decide?
 *  - moderator/admin: all reports (no limit)
 *  - volunteer: only reports inside the volunteer's active areas
 *  - anyone else, or a volunteer without an active area: nothing
 * A report's area = the issue's confirmed area if set, otherwise the
 * reporter's suggested area. A report with no area is not in any volunteer's scope.
 */
class ModerationScope
{
    /** null = no limit. array = allowed area ids (can be empty = nothing). */
    public static function areaIds(?User $user): ?array
    {
        if (! $user) {
            return [];
        }
        if ($user->isModerator()) {
            return null;
        }
        if ($user->role !== 'volunteer' || $user->is_banned) {
            return [];
        }

        $roots = $user->activeModeratorAreas()->pluck('admin_area_id')->all();
        $all = $roots;
        $level = $roots;

        for ($i = 0; $i < 6 && $level; $i++) {
            $level = AdminArea::whereIn('parent_id', $level)->pluck('id')->all();
            $all = array_merge($all, $level);
        }

        return array_values(array_unique(array_map('intval', $all)));
    }

    public static function restrict(Builder $query, ?User $user): Builder
    {
        $ids = self::areaIds($user);

        if ($ids === null) {
            return $query;
        }
        if (! $ids) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($w) use ($ids) {
            $w->whereHas('issue', fn ($i) => $i->whereIn('admin_area_id', $ids))
                ->orWhere(function ($x) use ($ids) {
                    $x->whereIn('suggested_area_id', $ids)
                        ->whereDoesntHave('issue', fn ($i) => $i->whereNotNull('admin_area_id'));
                });
        });
    }

    public static function allows(?User $user, ?Report $report): bool
    {
        $ids = self::areaIds($user);

        if ($ids === null) {
            return true;
        }
        if (! $report) {
            return false;
        }

        $areaId = $report->issue?->admin_area_id ?? $report->suggested_area_id;

        return $areaId !== null && in_array((int) $areaId, $ids, true);
    }

    public static function authorizeReport(?User $user, ?Report $report): void
    {
        abort_unless(self::allows($user, $report), 403);
    }

    /** A volunteer may only choose an area inside their own area. */
    public static function authorizeArea(?User $user, ?int $areaId): void
    {
        $ids = self::areaIds($user);

        if ($ids === null || $areaId === null) {
            return;
        }

        abort_unless(in_array($areaId, $ids, true), 403);
    }
}