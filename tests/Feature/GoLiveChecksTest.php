<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoLiveChecksTest extends TestCase
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

    public function test_system_page_shows_new_golive_checks(): void
    {
        $res = $this->actingAs($this->admin())->get(route('admin.system.index'));
        $res->assertOk()
            ->assertSee(__('issues.hc_cron'))
            ->assertSee(__('issues.hc_mail'))
            ->assertSee(__('issues.hc_backup_fresh'))
            ->assertSee(__('issues.hc_cron_never')); // no heartbeat yet in tests
    }

    public function test_cron_check_turns_green_with_fresh_heartbeat(): void
    {
        cache()->forever('scheduler_heartbeat', now()->toIso8601String());

        $res = $this->actingAs($this->admin())->get(route('admin.system.index'));
        $res->assertOk()->assertDontSee(__('issues.hc_cron_never'));
    }

    public function test_deployment_guide_mentions_subdomain(): void
    {
        $md = file_get_contents(base_path('DEPLOYMENT.md'));
        $this->assertStringContainsString('সাবডোমেইন', $md);
        $this->assertStringContainsString('schedule:run', $md);
        $this->assertStringContainsString('APP_URL=https://road.your-domain.com', $md);
    }
}
