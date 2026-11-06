<?php

namespace App\Http\Controllers;

use App\Models\AdminArea;
use Illuminate\Http\JsonResponse;

class AreaChildrenController extends Controller
{
    public function __invoke(AdminArea $area): JsonResponse
    {
        $isBn = app()->getLocale() === 'bn';

        $items = AdminArea::query()->where('parent_id', $area->id)
            ->orderBy('name_en')
            ->get()
            ->map(fn (AdminArea $a) => [
                'id'    => $a->id,
                'level' => $a->level,
                'label' => $isBn ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn),
            ])
            ->values();

        return response()->json($items);
    }
}