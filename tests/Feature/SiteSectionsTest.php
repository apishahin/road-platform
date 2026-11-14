<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteSectionsTest extends TestCase
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

    private function override(array $new): void
    {
        SiteSections::save($new);
    }

    public function test_everything_visible_by_default(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="home-map"', false)
            ->assertSee('id="issue-list"', false)
            ->assertSee('aria-label="Footer"', false)
            ->assertSee(__('issues.home_privacy'));
    }

    public function test_map_section_can_be_turned_off(): void
    {
        $this->override(['sections' => ['map' => ['on' => false]]]);
        $this->get('/')->assertOk()
            ->assertDontSee('id="home-map"', false)
            ->assertDontSee('id="issue-list"', false);
    }

    public function test_hero_can_be_hidden_and_customized(): void
    {
        $this->override(['sections' => ['hero' => ['on' => true, 'title' => 'ZZSITE Custom headline']]]);
        $this->get('/')->assertOk()->assertSee('ZZSITE Custom headline');

        $this->override(['sections' => ['hero' => ['on' => false]]]);
        $this->get('/')->assertOk()->assertDontSee('Browse issues');
    }

    public function test_stats_off_removes_counters(): void
    {
        $this->override(['sections' => ['stats' => ['on' => false]]]);
        $this->get('/')->assertOk()->assertDontSee(__('Open issues'));
    }

    public function test_heading_size_is_applied(): void
    {
        $this->override(['sections' => ['hero' => ['on' => true, 'size' => 'xlarge']]]);
        $this->get('/')->assertOk()->assertSee('text-3xl sm:text-4xl', false);
    }

    public function test_nav_report_button_can_be_hidden(): void
    {
        $this->get('/')->assertOk()->assertSee('bg-brand-600 px-3.5', false); // navbar report button present

        $this->override(['nav' => ['report' => false]]);
        $this->get('/')->assertOk()->assertDontSee('bg-brand-600 px-3.5', false);
    }

    public function test_fund_nav_link_appears_when_enabled(): void
    {
        // navbar fund link has a distinctive emerald style
        $this->get('/')->assertOk()->assertDontSee('text-emerald-700', false);

        $this->override(['nav' => ['fund' => true]]);
        $this->get('/')->assertOk()->assertSee('text-emerald-700', false);
    }

    public function test_footer_links_can_be_hidden_and_text_overridden(): void
    {
        $this->override(['footer' => ['links_on' => false, 'text' => 'ZZFOOT custom footer text']]);
        $this->get('/')->assertOk()
            ->assertDontSee('aria-label="Footer"', false)
            ->assertSee('ZZFOOT custom footer text');
    }

    public function test_admin_page_only_for_admins(): void
    {
        $this->get('/admin/site')->assertRedirect(); // guests → login
        $citizen = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone' => '0171' . random_int(1000000, 9999999),
            'role' => 'citizen',
        ]));
        $this->actingAs($citizen)->get('/admin/site')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/site')->assertOk();
    }

    public function test_admin_save_persists_settings(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/site', [
            'sec_hero_on'     => '1',
            'sec_hero_title'  => 'ZZSITE Saved title',
            'sec_hero_size'   => 'large',
            'sec_map_on'      => '1',
            'sec_how_on'      => '1',
            'sec_privacy_on'  => '1',
            'nav_home'        => '1',
            'nav_issues'      => '1',
            'nav_report'      => '1',
            'nav_lang'        => '1',
            'nav_fund'        => '1',
            'footer_links_on' => '1',
            'footer_text'     => 'ZZFOOT saved',
        ])->assertRedirect();

        $s = SiteSections::all();
        $this->assertSame('ZZSITE Saved title', $s['sections']['hero']['title']);
        $this->assertSame('large', $s['sections']['hero']['size']);
        $this->assertTrue($s['nav']['fund']);
        $this->assertSame('ZZFOOT saved', $s['footer']['text']);
        // unchecked = off
        $this->assertFalse($s['sections']['stats']['on']);

        $this->get('/')->assertOk()->assertSee('ZZSITE Saved title');
    }
}
