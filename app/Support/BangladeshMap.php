<?php

namespace App\Support;

use JsonException;

final class BangladeshMap
{
    private const MIN_LATITUDE = 20.60;
    private const MAX_LATITUDE = 26.65;
    private const MIN_LONGITUDE = 88.00;
    private const MAX_LONGITUDE = 92.72;

    /** @var list<array{outer: list<array{0: float, 1: float}>, holes: list<list<array{0: float, 1: float}>>}>|null */
    private static ?array $polygons = null;

    /**
     * Whether a latitude/longitude is inside the Bangladesh outline used by the
     * application's maps. This is a product boundary, not a legal boundary.
     */
    public static function contains(float $latitude, float $longitude): bool
    {
        if ($latitude < self::MIN_LATITUDE
            || $latitude > self::MAX_LATITUDE
            || $longitude < self::MIN_LONGITUDE
            || $longitude > self::MAX_LONGITUDE) {
            return false;
        }

        foreach (self::polygons() as $polygon) {
            if (! self::inRing($latitude, $longitude, $polygon['outer'])) {
                continue;
            }

            foreach ($polygon['holes'] as $hole) {
                if (self::inRing($latitude, $longitude, $hole)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /** @return list<array{outer: list<array{0: float, 1: float}>, holes: list<list<array{0: float, 1: float}>>}> */
    private static function polygons(): array
    {
        if (self::$polygons !== null) {
            return self::$polygons;
        }

        try {
            $geoJson = json_decode(
                (string) file_get_contents(public_path('maps/bangladesh-boundary.geojson')),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return self::$polygons = [];
        }

        $geometry = $geoJson['features'][0]['geometry'] ?? null;
        $coordinates = is_array($geometry) ? ($geometry['coordinates'] ?? null) : null;
        $type = is_array($geometry) ? ($geometry['type'] ?? null) : null;

        if (! is_array($coordinates)) {
            return self::$polygons = [];
        }

        $sourcePolygons = $type === 'Polygon' ? [$coordinates] : ($type === 'MultiPolygon' ? $coordinates : []);
        $polygons = [];

        foreach ($sourcePolygons as $sourcePolygon) {
            if (! is_array($sourcePolygon) || ! isset($sourcePolygon[0]) || ! is_array($sourcePolygon[0])) {
                continue;
            }

            $rings = [];

            foreach ($sourcePolygon as $ring) {
                if (! is_array($ring)) {
                    continue;
                }

                $normalisedRing = [];
                foreach ($ring as $point) {
                    if (! is_array($point) || ! isset($point[0], $point[1]) || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                        continue;
                    }

                    // GeoJSON stores [longitude, latitude]; map code uses [latitude, longitude].
                    $normalisedRing[] = [(float) $point[1], (float) $point[0]];
                }

                if (count($normalisedRing) >= 3) {
                    $rings[] = $normalisedRing;
                }
            }

            if ($rings === []) {
                continue;
            }

            $polygons[] = [
                'outer' => array_shift($rings),
                'holes' => $rings,
            ];
        }

        return self::$polygons = $polygons;
    }

    /** @param list<array{0: float, 1: float}> $ring */
    private static function inRing(float $latitude, float $longitude, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latA, $lngA] = $ring[$i];
            [$latB, $lngB] = $ring[$j];

            if (self::onSegment($latitude, $longitude, $latA, $lngA, $latB, $lngB)) {
                return true;
            }

            if ((($latA > $latitude) !== ($latB > $latitude))
                && ($longitude < (($lngB - $lngA) * ($latitude - $latA) / ($latB - $latA)) + $lngA)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function onSegment(float $lat, float $lng, float $latA, float $lngA, float $latB, float $lngB): bool
    {
        $crossProduct = (($lng - $lngA) * ($latB - $latA)) - (($lat - $latA) * ($lngB - $lngA));

        if (abs($crossProduct) > 0.000000001) {
            return false;
        }

        return $lat >= min($latA, $latB) - 0.000000001
            && $lat <= max($latA, $latB) + 0.000000001
            && $lng >= min($lngA, $lngB) - 0.000000001
            && $lng <= max($lngA, $lngB) + 0.000000001;
    }
}
