<?php

namespace App\Http\Controllers;

use App\Models\AdminArea;
use App\Services\HomeData;

class HomeController extends Controller
{
    public function index(HomeData $data)
    {
        $aliases = [
            'Barisal' => 'Barishal', 'Bogra' => 'Bogura', 'Brahamanbaria' => 'Brahmanbaria',
            'Chittagong' => 'Chattogram', 'Comilla' => 'Cumilla', 'Jessore' => 'Jashore',
            'Maulvibazar' => 'Moulvibazar', 'Nawabganj' => 'Chapainawabganj', 'Netrakona' => 'Netrokona',
        ];

        $labels = AdminArea::query()
            ->where('level', 'district')
            ->get(['name_en', 'name_bn'])
            ->mapWithKeys(fn (AdminArea $area) => [
                $area->name_en => app()->getLocale() === 'bn'
                    ? ($area->name_bn ?: $area->name_en)
                    : ($area->name_en ?: $area->name_bn),
            ]);

        foreach ($aliases as $boundaryName => $areaName) {
            $labels[$boundaryName] = $labels[$areaName] ?? $boundaryName;
        }

        return view('home', $data->build() + ['districtLabels' => $labels]);
    }
}