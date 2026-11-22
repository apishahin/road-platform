<?php

namespace Tests\Unit;

use Tests\TestCase;

class BangladeshDistrictMapTest extends TestCase
{
    public function test_local_district_map_has_all_64_districts(): void
    {
        $path = public_path('maps/bangladesh-districts.geojson');

        $this->assertFileExists($path);
        $map = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('FeatureCollection', $map['type']);
        $this->assertCount(64, $map['features']);
        $this->assertSame('Dhaka', collect($map['features'])->firstWhere('properties.shapeName', 'Dhaka')['properties']['shapeName']);
    }

    public function test_home_map_uses_the_local_bangladesh_district_layer_not_world_tiles(): void
    {
        $script = (string) file_get_contents(resource_path('js/home-map.js'));

        $this->assertStringContainsString('loadDistrictMap', $script);
        $this->assertStringContainsString('districtMapUrl', $script);
        $this->assertStringNotContainsString('L.tileLayer(', $script);
    }
}
