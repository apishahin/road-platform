<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_issues_page_loads(): void
    {
        $this->get('/issues')->assertOk();
    }

    public function test_home_page_exposes_its_local_bangladesh_district_map(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('bangladesh-districts.geojson', false)
            ->assertSee('districtMapUrl', false);
    }

    public function test_draft_page_is_404_for_guests(): void
    {
        Page::create(['slug' => 'draft-one', 'title_bn' => 'Draft', 'body_bn' => 'x', 'is_published' => false]);
        $this->get('/p/draft-one')->assertNotFound();
    }

    public function test_published_page_strips_script_and_unsafe_links(): void
    {
        Page::create([
            'slug' => 'safe-one',
            'title_bn' => 'Safe',
            'body_bn' => "Hello\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
            'is_published' => true,
        ]);

        $this->get('/p/safe-one')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false);
    }
}
