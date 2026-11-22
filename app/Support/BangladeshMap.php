<?php

namespace App\Support;

final class BangladeshMap
{
    /**
     * True when a coordinate falls in the application's Bangladesh map outline.
     * This outline is for product map limits; it is not a legal boundary dataset.
     */
    public static function contains(float $latitude, float $longitude): bool
    {
        $ring = (array) config('roadplatform.map.country_boundary', []);
        $count = count($ring);

        if ($count < 4) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $yi = (float) ($ring[$i][0] ?? 0);
            $xi = (float) ($ring[$i][1] ?? 0);
            $yj = (float) ($ring[$j][0] ?? 0);
            $xj = (float) ($ring[$j][1] ?? 0);

            if ((($yi > $latitude) !== ($yj > $latitude))
                && ($longitude < (($xj - $xi) * ($latitude - $yi) / ($yj - $yi)) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
