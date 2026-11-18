<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
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

    public function test_page_views_are_tracked_privately(): void
    {
        $this->get('/')->assertOk();

        $row = DB::table('page_views')->first();
        $this->assertNotNull($row);
        $this->assertSame('/', $row->path);
        $this->assertSame(16, strlen($row->vhash));

        // privacy: there is no IP column at all
        $this->assertSame(
            ['id', 'date', 'path', 'referrer', 'vhash', 'created_at'],
            \Illuminate\Support\Facades\Schema::getColumnListing('page_views')
        );
    }

    public function test_admin_bot_and_non_page_requests_are_not_tracked(): void
    {
        // health endpoint (non-HTML)
        $this->get('/up');
        $this->assertSame(0, DB::table('page_views')->count());

        // admin area
        $this->actingAs($this->admin())->get('/admin/system')->assertOk();
        $this->assertSame(0, DB::table('page_views')->count());

        auth()->logout();

        // normal public pages count
        $this->get('/')->assertOk();
        $this->get('/dashboard')->assertOk();
        $this->assertSame(2, DB::table('page_views')->count());

        // bot user-agent (kept last: server vars stick to later requests)
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/');
        $this->assertSame(2, DB::table('page_views')->count());
    }

    public function test_unique_visitors_count_daily_rotating_hashes(): void
    {
        $this->get('/');         // same visitor, same day
        $this->get('/issues');   // second page

        $this->assertSame(2, DB::table('page_views')->count());
        $this->assertSame(
            1,
            DB::table('page_views')->distinct()->count('vhash')
        );
    }

    public function test_admin_sees_the_analytics_dashboard(): void
    {
        DB::table('page_views')->insert([
            ['date' => now()->toDateString(), 'path' => '/', 'referrer' => 'facebook.com', 'vhash' => 'aaaaaaaaaaaaaaaa', 'created_at' => now()],
            ['date' => now()->toDateString(), 'path' => '/issues', 'referrer' => null, 'vhash' => 'bbbbbbbbbbbbbbbb', 'created_at' => now()],
            ['date' => now()->subDays(3)->toDateString(), 'path' => '/', 'referrer' => 'google.com', 'vhash' => 'cccccccccccccccc', 'created_at' => now()],
        ]);

        $this->actingAs($this->admin())->get('/admin/analytics')->assertOk()
            ->assertSee('data-analytics', false)
            ->assertSee('data-an-chart', false)
            ->assertSee('data-an-pages', false)
            ->assertSee('data-an-refs', false)
            ->assertSee('facebook.com')
            ->assertSee('/issues');
    }

    public function test_analytics_is_admin_only(): void
    {
        $this->get('/admin/analytics')->assertRedirect('/login');

        $citizen = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(), 'phone' => '0172' . random_int(1000000, 9999999), 'role' => 'citizen',
        ]));
        $this->actingAs($citizen)->get('/admin/analytics')->assertForbidden();
    }
}
