<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FloatingButtonsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0171' . random_int(1000000, 9999999),
            'role'      => 'admin',
        ]));
    }

    public function test_homepage_shows_all_three_floating_buttons_with_defaults(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-pwa-pill hidden', false)   // install pill
            ->assertSee('data-rp-fab', false)     // report FAB
            ->assertSee('data-rp-top', false)     // back-to-top
            ->assertSee('#059669', false)         // default pill/FAB colour
            ->assertSee('#334155', false);        // default scroll-top colour
    }

    public function test_admin_can_customize_and_toggle_floating_buttons(): void
    {
        $this->actingAs($this->admin())->post('/admin/site', [
            'float_pill_on'    => '1',
            'float_pill_label' => 'Install now!',
            'float_pill_color' => '#123456',
            'float_pill_size'  => 'lg',
            'float_pill_pos'   => 'bl',
            // fab_on absent => off
            'float_fab_color'  => '#ff0000',
            'float_fab_size'   => 'sm',
            'float_fab_pos'    => 'br',
            'float_top_on'     => '1',
            'float_top_color'  => '#654321',
            'float_top_size'   => 'sm',
            'float_top_pos'    => 'bc',
        ])->assertRedirect(route('admin.site.index'));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Install now!', $html);
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('left:16px;bottom:16px;', $html); // pill moved left
        $this->assertStringNotContainsString('data-rp-fab', $html);        // FAB hidden
        $this->assertStringContainsString('#654321', $html);               // top recoloured
        $this->assertStringContainsString('data-rp-top', $html);
    }

    public function test_admin_can_turn_off_pill_and_top(): void
    {
        $this->actingAs($this->admin())->post('/admin/site', [
            'float_fab_on'   => '1',
            'float_fab_size' => 'md',
            'float_fab_pos'  => 'br',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-pwa-pill hidden', $html);
        $this->assertStringNotContainsString('data-rp-top', $html);
        $this->assertStringContainsString('data-rp-fab', $html);
    }

    public function test_guest_is_redirected_from_site_save(): void
    {
        $this->post('/admin/site', [])->assertRedirect('/login');
    }

    public function test_bad_colour_is_rejected_by_validation(): void
    {
        $this->actingAs($this->admin())->post('/admin/site', [
            'float_fab_on'   => '1',
            'float_fab_color' => 'red',
            'float_fab_size' => 'md',
            'float_fab_pos'  => 'br',
        ])->assertSessionHasErrors('float_fab_color');
    }
}
