<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use App\Support\SiteSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeIssue(int $areaId, string $status = 'reported', ?\Carbon\Carbon $at = null, string $disposition = 'active'): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'dash-cat'],
            ['name_bn' => 'ড্যাশ-বিভাগ', 'name_en' => 'DashCat', 'sort' => 1, 'is_active' => 1]
        ));
        $at ??= now();
        $issue = Issue::create([
            'title' => 'Dash issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $areaId,
            'progress_status' => $status, 'verification_status' => 'unverified',
            'disposition' => $disposition, 'reports_count' => 1,
            'first_reported_at' => $at, 'last_activity_at' => $at,
        ]);
        $issue->forceFill(['published_at' => $at->toDateTimeString()])->save();

        return $issue;
    }

    public function test_dashboard_is_public_and_shows_live_counts(): void
    {
        $dist = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'জেলা-টেস্ট', 'name_en' => 'ZZDistrict']);
        $upz  = AdminArea::create(['parent_id' => $dist->id, 'level' => 'upazila', 'name_bn' => 'উপজেলা', 'name_en' => 'ZZUpazila']);

        $this->makeIssue($upz->id);                 // open, pinned at upazila → walks up to district
        $this->makeIssue($upz->id, 'repaired');     // repaired
        $this->makeIssue($dist->id, 'reported', null, 'rejected'); // not active → excluded

        $u = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(), 'phone' => '0171' . random_int(1000000, 9999999),
        ]));
        Report::create([
            'issue_id' => Issue::first()->id, 'user_id' => $u->id,
            'description' => 'd', 'lat' => 23.8, 'lng' => 90.4, 'moderation' => 'approved',
        ]);

        $html = $this->get('/dashboard')->assertOk()
            ->assertSee('data-dashboard', false)
            ->assertSee(app()->getLocale() === 'bn' ? 'জেলা-টেস্ট' : 'ZZDistrict') // district row via walk-up
            ->getContent();
        $this->assertStringNotContainsString('ZZUpazila', $html); // the upazila itself is not listed
        $this->assertStringNotContainsString('উপজেলা', $html);
        $this->assertStringContainsString('data-district-row', $html);
    }

    public function test_admin_controls_home_stats_panel_and_chart_styles(): void
    {
        $dist = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ক', 'name_en' => 'ZZD']);
        $this->makeIssue($dist->id);

        // panel ON with donut category chart + horizontal trend bars
        SiteSections::save(['sections' => ['ribbon' => [
            'on' => true, 'cat_style' => 'donut', 'trend_style' => 'hbars',
        ]]]);
        $this->get('/')->assertOk()
            ->assertSee('data-stats-panel', false)
            ->assertSee('data-catchart="donut"', false)
            ->assertSee('data-trend="hbars"', false);

        // trend and category panels can be switched off individually
        SiteSections::save(['sections' => ['ribbon' => [
            'on' => true, 'show_trend' => false, 'show_categories' => false,
        ]]]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-stats-panel', $html);       // counters still there
        $this->assertStringNotContainsString('data-trend=', $html);          // trend hidden
        $this->assertStringNotContainsString('data-catchart=', $html);       // category hidden

        // whole panel off
        SiteSections::save(['sections' => ['ribbon' => ['on' => false]]]);
        $this->get('/')->assertOk()->assertDontSee('data-stats-panel', false);

        // invalid chart style is rejected by validation
        $admin = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(), 'phone' => '0171' . random_int(1000000, 9999999), 'role' => 'admin',
        ]));
        $this->actingAs($admin)->post('/admin/site', ['sec_ribbon_cat_style' => 'piechart'])->assertSessionHasErrors();
    }

    public function test_line_trend_district_donut_and_custom_colours_render(): void
    {
        $dist = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ক', 'name_en' => 'ZZD']);
        $this->makeIssue($dist->id);

        SiteSections::save(['sections' => ['ribbon' => [
            'on' => true, 'trend_style' => 'line', 'dist_style' => 'donut',
            'cat_style' => 'pie', 'color1' => '#a855f7', 'color2' => '#f59e0b',
        ]]]);

        $this->get('/')->assertOk()
            ->assertSee('data-trend="line"', false)
            ->assertSee('data-distchart="donut"', false)
            ->assertSee('data-catchart="pie"', false)
            ->assertSee('<polyline', false)          // line chart svg
            ->assertSee('#a855f7', false);          // custom colour applied

        // a bad colour is refused and falls back to the default
        SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'color1' => 'red']]]);

        $this->get('/')->assertOk()->assertSee('#22c55e', false);
    }

    public function test_dashboard_empty_state_and_nav_toggle(): void
    {
        // no data → friendly empty state, still 200
        $this->get('/dashboard')->assertOk()->assertSee(__('issues.dash_empty'));

        // desktop nav link on by default, and the admin can switch it off
        $marker = 'href="' . route('dashboard') . '" class=';
        $this->get('/')->assertOk()->assertSee($marker, false);
        SiteSections::save(['nav' => ['dashboard' => false]]);
        $this->get('/')->assertOk()->assertDontSee($marker, false);
    }
}
