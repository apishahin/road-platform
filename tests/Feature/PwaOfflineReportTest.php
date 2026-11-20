<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PwaOfflineReportTest extends TestCase
{
    use RefreshDatabase;

    private function citizen(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0181' . random_int(1000000, 9999999),
            'role'      => 'citizen',
        ]));
    }

    public function test_manifest_is_served_with_icons(): void
    {
        $res = $this->get('/manifest.json');
        $res->assertOk();
        $json = $res->json();
        $this->assertSame('standalone', $json['display']);
        $this->assertCount(4, $json['icons']);
        $this->assertNotEmpty($json['name']);
    }

    public function test_service_worker_v3_caches_report_and_replays_queue(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("pwa-v3", $sw);
        $this->assertStringContainsString("'/report'", $sw);
        $this->assertStringContainsString('report-queue', $sw);
        $this->assertStringContainsString("addEventListener('sync'", $sw);
        $this->assertStringContainsString('/report/success', $sw);
    }

    public function test_report_page_carries_offline_queue(): void
    {
        $res = $this->actingAs($this->citizen())->get(route('report.create'));
        $res->assertOk()
            ->assertSee('report-queue', false)      // SW sync registration
            ->assertSee('rq-card', false)           // pending-queue card
            ->assertSee('indexedDB', false)         // offline storage
            ->assertSee('SyncManager', false);
    }

    public function test_offline_page_exists_for_pwa_fallback(): void
    {
        $this->get('/offline')->assertOk();
    }
}
