<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdsTest extends TestCase
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

    public function test_ads_are_off_by_default_and_render_nothing(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('adsbygoogle', false)
            ->assertDontSee('data-ad-slot', false);

        $this->get('/ads.txt')->assertNotFound();
    }

    public function test_admin_page_and_validation(): void
    {
        $this->get('/admin/ads')->assertRedirect('/login');

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/ads')->assertOk()->assertSee('data-ads-panel', false);

        // bad publisher id is rejected
        $this->actingAs($admin)->post('/admin/ads', ['on' => '1', 'publisher_id' => 'not-a-pub-id'])
            ->assertSessionHasErrors();

        // good settings save
        $this->actingAs($admin)->post('/admin/ads', [
            'on'           => '1',
            'publisher_id' => 'ca-pub-1234567890123456',
            'ads_txt'      => "google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0",
            'slots'        => ['home_after_hero' => '<ins class="adsbygoogle">X</ins>'],
        ])->assertSessionHasNoErrors()->assertSessionHas('status');
    }

    public function test_enabled_ads_render_script_slot_and_ads_txt_for_guests(): void
    {
        AdSettings::save([
            'on'             => true,
            'hide_for_staff' => false,
            'publisher_id'   => 'ca-pub-1234567890123456',
            'ads_txt'        => 'google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0',
            'slots'          => ['above_footer' => '<ins class="adsbygoogle">FOOTER-AD</ins>'],
        ]);

        $this->get('/')->assertOk()
            ->assertSee('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456', false)
            ->assertSee('FOOTER-AD', false)
            ->assertSee('data-ad-slot="above_footer"', false);

        $this->get('/ads.txt')->assertOk()
            ->assertSee('google.com, pub-1234567890123456');
    }

    public function test_staff_can_be_hidden_from_ads(): void
    {
        AdSettings::save([
            'on'             => true,
            'hide_for_staff' => true,
            'publisher_id'   => 'ca-pub-1234567890123456',
            'slots'          => ['above_footer' => '<ins>FOOTER-AD</ins>'],
        ]);

        // guests see ads
        $this->get('/')->assertSee('FOOTER-AD', false);

        // admins do not
        $this->actingAs($this->admin())->get('/')->assertDontSee('FOOTER-AD', false)
            ->assertDontSee('data-ad-slot', false);
    }

    public function test_analytics_date_range_filters_the_report(): void
    {
        \Illuminate\Support\Facades\DB::table('page_views')->insert([
            ['date' => now()->toDateString(), 'path' => '/', 'referrer' => null, 'vhash' => 'aaaaaaaaaaaaaaaa', 'created_at' => now()],
            ['date' => now()->toDateString(), 'path' => '/', 'referrer' => null, 'vhash' => 'bbbbbbbbbbbbbbbb', 'created_at' => now()],
            ['date' => now()->subDays(10)->toDateString(), 'path' => '/', 'referrer' => null, 'vhash' => 'cccccccccccccccc', 'created_at' => now()],
        ]);

        $admin = $this->admin();

        // default (30 days) covers all three
        $this->actingAs($admin)->get('/admin/analytics')->assertOk()
            ->assertSee('>3</div>', false);

        // today-only range covers exactly the two of today
        $t = now()->toDateString();
        $this->actingAs($admin)->get("/admin/analytics?from=$t&to=$t")->assertOk()
            ->assertSee('>2</div>', false)
            ->assertSee('data-an-range', false);

        // invalid range falls back to the default instead of erroring
        $this->actingAs($admin)->get('/admin/analytics?from=garbage&to=2026-99-99')->assertOk()
            ->assertSee('>3</div>', false);
    }
}
