<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use Illuminate\Http\JsonResponse;

class AreaChildrenController extends Controller
{
    public function __invoke(AdminArea $area): JsonResponse
    {
        abort_unless(auth()->user()?->isModerator(), 403);

        $isBn = app()->getLocale() === 'bn';

        $items = AdminArea::where('parent_id', $area->id)
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