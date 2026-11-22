<?php

namespace Tests\Unit;

use App\Support\BangladeshMap;
use Tests\TestCase;

class BangladeshMapTest extends TestCase
{
    public function test_it_accepts_a_coordinate_inside_bangladesh(): void
    {
        $this->assertTrue(BangladeshMap::contains(23.8103, 90.4125)); // Dhaka
    }

    public function test_it_rejects_a_coordinate_in_the_old_rectangular_but_outside_country_area(): void
    {
        $this->assertFalse(BangladeshMap::contains(22.5726, 88.3639)); // Kolkata
    }

    public function test_it_rejects_a_coordinate_outside_the_country_bounds(): void
    {
        $this->assertFalse(BangladeshMap::contains(16.8409, 96.1735)); // Yangon
    }
}
