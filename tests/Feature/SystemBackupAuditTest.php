<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\BackupService;
use App\Support\SystemState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemBackupAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'phone'     => '0171'.random_int(1000000, 9999999),
            'role'      => 'admin',
        ]));
    }

    private function citizen(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'phone'     => '0181'.random_int(1000000, 9999999),
            'role'      => 'citizen',
        ]));
    }

    private function backups(): array
    {
        return glob(storage_path('app/backups/*.zip')) ?: [];
    }

    protected function tearDown(): void
    {
        SystemState::saveAutoBackup(true);
        parent::tearDown();
    }

    public function test_auto_backup_defaults_on_and_toggle_works(): void
    {
        $this->assertTrue(SystemState::autoBackupOn());

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.system.autobackup'), ['on' => '1'])
            ->assertRedirect();
        $this->assertTrue(SystemState::autoBackupOn());

        $this->actingAs($admin)->post(route('admin.system.autobackup'), [])
            ->assertRedirect();
        $this->assertFalse(SystemState::autoBackupOn());

        // Maintenance flag must survive the toggle (merge, not overwrite)
        $this->assertFalse(SystemState::maintenance()['on']);

        // Toggle visible on system page
        $this->actingAs($admin)->get(route('admin.system.index'))
            ->assertOk()->assertSee('name="on"', false);
    }

    public function test_backup_run_command_creates_auto_backup(): void
    {
        $before = $this->backups();
        $this->artisan('backup:run')->assertSuccessful();
        $after = array_diff($this->backups(), $before);
        $this->assertNotEmpty($after);
        $this->assertStringContainsString('auto-', basename(reset($after)));
        foreach ($after as $f) {
            @unlink($f);
        }
    }

    public function test_prune_auto_keeps_only_ten(): void
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $made = [];
        for ($i = 0; $i < 12; $i++) {
            $f = $dir.'/auto-2099-01-01-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT).'-prune-test.zip';
            file_put_contents($f, 'x');
            $made[] = $f;
        }

        app(BackupService::class)->pruneAuto();

        $left = glob($dir.'/auto-2099-01-01-*-prune-test.zip');
        $this->assertCount(BackupService::AUTO_KEEP, $left);
        foreach ($left as $f) {
            @unlink($f);
        }
    }

    public function test_audit_log_page_lists_events_for_admin_only(): void
    {
        AuditEvent::create([
            'actor_id' => null, 'action' => 'system.settings.update',
            'target_type' => 'system', 'target_id' => 'settings', 'details' => ['x' => 1], 'ip_address' => '127.0.0.1',
        ]);

        $this->get(route('admin.auditlog.index'))->assertRedirect();

        $user = $this->citizen();
        $this->actingAs($user)->get(route('admin.auditlog.index'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.auditlog.index'))
            ->assertOk()
            ->assertSee('data-audit-log', false)
            ->assertSee('system.settings.update');
    }

    public function test_analytics_csv_export(): void
    {
        $this->get(route('admin.analytics.csv'))->assertRedirect('/login');

        $admin = $this->admin();
        $this->get('/?probe=csv');
        $this->get('/?probe=csv2');

        $res = $this->actingAs($admin)->get(route('admin.analytics.csv', [
            'from' => now()->toDateString(), 'to' => now()->toDateString(),
        ]));
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $res->streamedContent();
        $this->assertStringContainsString('date,views,unique_visitors', $body);
        $this->assertStringContainsString(now()->toDateString(), $body);

        $staff = $this->citizen();
        $this->actingAs($staff)->get(route('admin.analytics.csv'))->assertForbidden();
    }
}
