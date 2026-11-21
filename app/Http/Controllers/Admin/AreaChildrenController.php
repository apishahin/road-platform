<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Support\ModerationScope;
use Illuminate\Http\JsonResponse;

class AreaChildrenController extends Controller
{
    public function __invoke(AdminArea $area): JsonResponse
    {
        abort_unless(auth()->user()?->canModerate(), 403);

        $isBn = app()->getLocale() === 'bn';
        $branchIds = ModerationScope::branchIds(auth()->user());
        if ($branchIds !== null) {
            abort_unless(in_array((int) $area->id, $branchIds, true), 403);
        }

        $items = AdminArea::where('parent_id', $area->id)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('id', $branchIds))
            ->orderBy('name_en')
            ->get()
            ->map(fn ($a) => [
                'id'        => $a->id,
                'parent_id' => $a->parent_id,
                'level'     => $a->level,
                'label'     => $isBn ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn),
            ])
            ->values();

        return response()->json($items);
    }
}