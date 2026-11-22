<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppearanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapDisplaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppearanceSettings::reset();
    }

    protected function tearDown(): void
    {
        AppearanceSettings::reset();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create([
            'phone' => '+8801900000171',
            'display_name' => 'Map administrator',
            'role' => 'admin',
        ]);
    }

    private function payload(string $display): array
    {
        return [
            'font' => 'system',
            'size' => 'normal',
            'brand' => '#ea580c',
            'header_bg' => '#ffffff',
            'header_text' => '#475569',
            'name_bn' => '',
            'name_en' => '',
            'issue_view' => 'simple',
            'map_display' => $display,
        ];
    }

    public function test_an_administrator_can_save_world_browsing_for_every_map(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/site')
            ->assertOk()
            ->assertSee('name="map_display"', false)
            ->assertSee('value="world"', false);

        $this->actingAs($admin)->post('/admin/appearance', $this->payload('world'))
            ->assertRedirect(route('admin.appearance.index'));

        $this->assertSame('world', AppearanceSettings::mapDisplay());
        $this->get('/')
            ->assertOk()
            ->assertSee('roadPlatformMapDisplay', false)
            ->assertSee('world', false);
    }

    public function test_invalid_map_display_is_rejected_and_the_safe_default_is_bangladesh(): void
    {
        $admin = $this->admin();

        $this->assertSame('bangladesh', AppearanceSettings::mapDisplay());
        $this->actingAs($admin)->post('/admin/appearance', $this->payload('elsewhere'))
            ->assertSessionHasErrors('map_display');
        $this->assertSame('bangladesh', AppearanceSettings::mapDisplay());
    }

    public function test_world_browsing_does_not_relax_the_bangladesh_only_api_rule(): void
    {
        AppearanceSettings::save(['map_display' => 'world']);

        $this->getJson('/api/v1/issues/nearby?lat=22.5726&lng=88.3639')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lat');
    }

    public function test_only_an_administrator_can_change_the_global_map_display(): void
    {
        $citizen = User::create([
            'phone' => '+8801900000172',
            'display_name' => 'Citizen',
            'role' => 'citizen',
        ]);

        $this->actingAs($citizen)->post('/admin/appearance', $this->payload('world'))
            ->assertForbidden();
        $this->assertSame('bangladesh', AppearanceSettings::mapDisplay());
    }
}
