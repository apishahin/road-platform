<?php

namespace Tests\Feature;

use App\Models\{AdminArea, Category, Issue, User};
use App\Support\SponsorSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SponsorsImpactTest extends TestCase
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

    private function makeIssue(string $progress = 'reported'): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'si-cat'],
            ['name_bn' => 'SI', 'name_en' => 'SI', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $issue = Issue::create([
            'title' => 'SI issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => $progress, 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    public function test_sponsors_off_by_default_and_home_renders_nothing(): void
    {
        $this->assertFalse(SponsorSettings::on());
        $this->get('/')->assertOk()->assertDontSee('data-sponsors', false);
    }

    public function test_admin_can_add_and_home_renders_when_on(): void
    {
        $this->get(route('admin.sponsors.index'))->assertRedirect('/login');

        $user = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0181' . random_int(1000000, 9999999),
            'role'      => 'citizen',
        ]));
        $this->actingAs($user)->get(route('admin.sponsors.index'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.sponsors.index'))
            ->assertOk()->assertSee('data-sponsors-admin', false);

        $this->actingAs($admin)->post(route('admin.sponsors.add'), [
            'name' => 'Test Partner', 'url' => 'https://example.org',
        ])->assertRedirect();

        // still hidden while section is off
        $this->get('/')->assertDontSee('Test Partner');

        $this->actingAs($admin)->post(route('admin.sponsors.save'), ['on' => '1', 'title' => '']);
        $this->assertTrue(SponsorSettings::on());

        $this->get('/')->assertOk()
            ->assertSee('data-sponsors', false)
            ->assertSee('Test Partner')
            ->assertSee('https://example.org', false);

        // bad URL rejected by validation
        $this->actingAs($admin)->post(route('admin.sponsors.add'), [
            'name' => 'Bad', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        // delete
        $this->actingAs($admin)->post(route('admin.sponsors.delete'), ['index' => 0]);
        $this->assertCount(0, SponsorSettings::items());
    }

    public function test_impact_page_is_public_and_shows_stats(): void
    {
        $this->makeIssue();
        $this->makeIssue();
        $this->makeIssue('repaired');

        $res = $this->get(route('impact'));
        $res->assertOk()->assertSee('data-impact', false)
            ->assertSee(__('issues.impact_total'))
            ->assertSee('3');
    }

    public function test_footer_has_impact_link(): void
    {
        $this->get('/')->assertOk()->assertSee(route('impact'), false);
    }
}
