<?php

namespace App\Http\Controllers;

use App\Models\AdminArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AreaLocateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return response()->json(['error' => 'bad_coordinates'], 422);
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        $path = base_path('database/data/area-points.csv');
        if (! is_file($path)) {
            return response()->json(['error' => 'no_points'], 503);
        }

        $points = Cache::remember('area_points_v1', 3600, function () use ($path) {
            $fh = fopen($path, 'r');
            fgetcsv($fh, 0, ',', '"', '');
            $rows = [];
            while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                if (count($r) < 6 || $r[0] === '' || $r[1] === '') {
                    continue;
                }
                $rows[] = [(float) $r[0], (float) $r[1], $r[2], $r[3], $r[4], $r[5]];
            }
            fclose($fh);
            return $rows;
        });

        if ($points === []) {
            return response()->json(['error' => 'no_points'], 503);
        }

        $best = null;
        $bestD = PHP_FLOAT_MAX;
        $cos = cos(deg2rad($lat));
        foreach ($points as $p) {
            $dy = $p[0] - $lat;
            $dx = ($p[1] - $lng) * $cos;
            $d = $dy * $dy + $dx * $dx;
            if ($d < $bestD) {
                $bestD = $d;
                $best = $p;
            }
        }

        $isBn = app()->getLocale() === 'bn';
        $pick = fn (?AdminArea $a) => $a ? [
            'id'    => $a->id,
            'label' => $isBn ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn),
        ] : null;

        $division = AdminArea::query()->where('level', 'division')->where('name_en', $best[2])->first();
        $district = $division ? AdminArea::query()->where('level', 'district')->where('parent_id', $division->id)->where('name_en', $best[3])->first() : null;
        $upazila  = $district ? AdminArea::query()->where('level', 'upazila')->where('parent_id', $district->id)->where('name_en', $best[4])->first() : null;
        $union    = $upazila  ? AdminArea::query()->where('level', 'union')->where('parent_id', $upazila->id)->where('name_en', $best[5])->first() : null;

        return response()->json([
            'division' => $pick($division),
            'district' => $pick($district),
            'upazila'  => $pick($upazila),
            'union'    => $pick($union),
        ]);
    }
}