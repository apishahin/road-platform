<?php

namespace App\Services;

use App\Models\Issue;
use Illuminate\Database\Eloquent\Collection;

class DuplicateService
{
    /**
     * Find nearby issues that might be the same problem.
     * Returns issues within $meters, ordered by distance,
     * with the same category first.
     *
     * This only SUGGESTS matches. A human decides.
     */
    public function suggest(
        float $lat,
        float $lng,
        int   $categoryId,
        int   $radiusMeters = 150,
        int   $limit = 5,
    ): Collection {
        return Issue::query()
            ->active()
            ->nearby($lat, $lng, $radiusMeters)
            ->where('category_id', $categoryId)
            ->orderByRaw('(category_id = ?) DESC', [$categoryId])
            ->limit($limit)
            ->get();
    }

    /**
     * Check whether an issue exists very close to a point
     * (within 30 metres). Used to prevent obvious duplicates
     * at report time.
     */
    public function findNearExact(float $lat, float $lng, int $categoryId): ?Issue
    {
        return Issue::query()
            ->active()
            ->nearby($lat, $lng, 30)
            ->where('category_id', $categoryId)
            ->first();
    }

    /**
     * Mark an issue as a duplicate of another issue.
     * Records the disposition change and the duplicate link.
     */
    public function markAsDuplicate(
        Issue $duplicate,
        Issue $target,
        string $reason,
    ): void {
        $statusService = app(IssueStatusService::class);
        $actor = auth()->user();

        $statusService->change(
            $duplicate,
            'disposition',
            'duplicate',
            $actor,
            "Marked as duplicate of issue #{$target->public_id}. {$reason}",
        );

        $duplicate->update([
            'duplicate_of_id' => $target->id,
        ]);
    }
}