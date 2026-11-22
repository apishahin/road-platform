<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BangladeshMapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearby_lookup_rejects_a_location_outside_bangladesh(): void
    {
        $this->getJson('/api/v1/issues/nearby?lat=22.5726&lng=88.3639') // Kolkata
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lat');
    }

    public function test_report_submission_rejects_a_point_outside_the_outline(): void
    {
        $user = User::create([
            'phone' => '+8801900000099',
            'display_name' => 'Map test user',
            'role' => 'citizen',
        ]);
        $category = Category::create([
            'slug' => 'map-test',
            'name_bn' => 'মানচিত্র পরীক্ষা',
            'name_en' => 'Map test',
            'sort' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post('/report', [
            'lat' => 22.5726, // Kolkata is within the old rectangle but outside Bangladesh.
            'lng' => 88.3639,
            'category_id' => $category->id,
            'title' => 'Outside country test',
            'description' => 'This coordinate must not be accepted.',
        ])->assertSessionHasErrors('lat');
    }
}
