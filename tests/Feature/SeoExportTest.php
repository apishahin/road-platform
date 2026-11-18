<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoExportTest extends TestCase
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

    private function makeIssue(bool $published): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'seo-cat'],
            ['name_bn' => 'সিও-বিভাগ', 'name_en' => 'SeoCat', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'সিও-জেলা', 'name_en' => 'SeoDistrict']);
        $issue = Issue::create([
            'title' => 'SEO টেস্ট ইস্যু ' . Str::random(5), 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill([
            'published_at'   => $published ? now()->toDateTimeString() : null,
            'unpublished_at' => $published ? null : now()->toDateTimeString(),
        ])->save();

        return $issue;
    }

    public function test_sitemap_lists_public_pages_and_published_issues_only(): void
    {
        $pub   = $this->makeIssue(true);
        $unpub = $this->makeIssue(false);

        $res = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $c = $res->getContent();
        $this->assertStringContainsString('/issues/' . $pub->public_id, $c);
        $this->assertStringNotContainsString('/issues/' . $unpub->public_id, $c);
        $this->assertStringContainsString(route('issues.index'), $c);
        $this->assertStringContainsString(route('dashboard'), $c);
    }

    public function test_robots_txt_points_to_sitemap_and_shields_admin(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap:')
            ->assertSee('/sitemap.xml')
            ->assertSee('Disallow: /admin', false);
    }

    public function test_pages_carry_full_social_share_tags(): void
    {
        $issue = $this->makeIssue(true);

        // home: full OG + twitter card + canonical, fallback share image
        $this->get('/')->assertOk()
            ->assertSee('name="twitter:card"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('property="og:url"', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('icons/icon-512.png', false);

        // issue page: its own title/URL in the tags
        $this->get('/issues/' . $issue->public_id)->assertOk()
            ->assertSee('property="og:title"', false)
            ->assertSee($issue->title);
    }

    public function test_admin_exports_filtered_issues_as_excel_friendly_csv(): void
    {
        $admin = $this->admin();
        $pub   = $this->makeIssue(true);
        $unpub = $this->makeIssue(false);

        $res = $this->actingAs($admin)->get('/admin/issues-export.csv')->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);   // BOM → Excel shows Bangla
        $this->assertStringContainsString('public_id', $csv);
        $this->assertStringContainsString($pub->title, $csv);
        $this->assertStringContainsString('সিও-জেলা', $csv);
        $this->assertStringNotContainsString($unpub->public_id, $csv);

        // the unpublished tab exports the other list
        $csv2 = $this->actingAs($admin)->get('/admin/issues-export.csv?tab=unpublished')->streamedContent();
        $this->assertStringContainsString($unpub->public_id, $csv2);
        $this->assertStringNotContainsString($pub->public_id, $csv2);
    }

    public function test_export_is_admin_only(): void
    {
        $this->get('/admin/issues-export.csv')->assertRedirect('/login');

        $citizen = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(), 'phone' => '0172' . random_int(1000000, 9999999), 'role' => 'citizen',
        ]));
        $this->actingAs($citizen)->get('/admin/issues-export.csv')->assertForbidden();
    }
}
