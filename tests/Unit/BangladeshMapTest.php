<?php

namespace Tests\Unit;

use App\Support\BangladeshMap;
use Tests\TestCase;

class BangladeshMapTest extends TestCase
{
    public function test_the_detailed_boundary_asset_is_available(): void
    {
        $path = public_path('maps/bangladesh-boundary.geojson');

        $this->assertFileExists($path);

        $geoJson = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $geometry = $geoJson['features'][0]['geometry'] ?? [];

        $this->assertSame('MultiPolygon', $geometry['type'] ?? null);
        $this->assertGreaterThan(1, count($geometry['coordinates'] ?? []));
    }

    public function test_it_accepts_a_coordinate_inside_bangladesh(): void
    {
        $this->assertTrue(BangladeshMap::contains(23.8103, 90.4125)); // Dhaka
        $this->assertTrue(BangladeshMap::contains(22.3569, 91.7832)); // Chattogram
    }

    public function test_it_rejects_coordinates_outside_the_real_outline(): void
    {
        $this->assertFalse(BangladeshMap::contains(22.5726, 88.3639)); // Kolkata
        $this->assertFalse(BangladeshMap::contains(16.8409, 96.1735)); // Yangon
    }
}
