<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemPanelTest extends TestCase
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

    /** Make a deliberately named archive so a test can emulate an older backup left on disk. */
    private function archive(string $name, string $driver, string $sql): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $name;
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true);
        $zip->addFromString('database.sql', $sql);
        $zip->addFromString('backup.json', json_encode(['db_driver' => $driver]));
        $zip->close();

        return $path;
    }

    public function test_guests_and_non_admins_cannot_open_the_system_panel(): void
    {
        $this->get('/admin/system')->assertRedirect('/login');

        $citizen = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(), 'phone' => '0172' . random_int(1000000, 9999999), 'role' => 'citizen',
        ]));
        $this->actingAs($citizen)->get('/admin/system')->assertForbidden();
        $this->actingAs($citizen)->post('/admin/system/cache', ['type' => 'views'])->assertForbidden();
        $this->actingAs($citizen)->post('/admin/system/backups')->assertForbidden();
    }

    public function test_admin_sees_info_health_cache_maintenance_update_and_backup_cards(): void
    {
        $this->actingAs($this->admin())->get('/admin/system')->assertOk()
            ->assertSee('data-system-panel', false)
            ->assertSee('data-health', false)
            ->assertSee('data-info', false)
            ->assertSee('data-cache', false)
            ->assertSee('data-maintenance', false)
            ->assertSee('data-update', false)
            ->assertSee('data-backup', false)
            ->assertSee(PHP_VERSION);
    }

    public function test_cache_clear_actions_and_log_truncate(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/system/cache', ['type' => 'views'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        file_put_contents(storage_path('logs/laravel.log'), 'x');
        $this->actingAs($admin)->post('/admin/system/cache', ['type' => 'log'])
            ->assertSessionHas('status');
        $this->assertSame(0, (int) filesize(storage_path('logs/laravel.log')));

        $this->actingAs($admin)->post('/admin/system/cache', ['type' => 'bogus'])
            ->assertSessionHasErrors();
    }

    public function test_maintenance_mode_blocks_visitors_but_not_login_or_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/system/maintenance', ['on' => '1', 'message' => 'টেস্ট বার্তা'])
            ->assertSessionHasNoErrors();
        auth()->logout();

        // visitors get a friendly 503 with the custom message
        $this->get('/')->assertStatus(503)->assertSee('টেস্ট বার্তা');
        // login flow stays reachable so admins are never locked out
        $this->get('/login')->assertOk();

        // admins work normally
        $this->actingAs($admin)->get('/')->assertOk();
        $this->actingAs($admin)->get('/admin/system')->assertOk();

        // back to normal
        $this->actingAs($admin)->post('/admin/system/maintenance', ['on' => '0'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/')->assertOk();
    }

    public function test_backup_create_download_and_delete(): void
    {
        $admin = $this->admin();

        // other tests may have left backups behind — track only the new one
        $before = glob(storage_path('app/backups/*.zip')) ?: [];

        $this->actingAs($admin)->post('/admin/system/backups')
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $files = array_values(array_diff(glob(storage_path('app/backups/*.zip')) ?: [], $before));
        $this->assertCount(1, $files);
        $name = basename($files[0]);

        // the zip really is a full backup with a SQL dump inside
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($files[0]) === true);
        $this->assertNotFalse($zip->getFromName('database.sql'));
        $this->assertNotFalse($zip->getFromName('backup.json'));
        $zip->close();

        $this->actingAs($admin)->get('/admin/system/backups/' . $name)->assertOk();
        $this->actingAs($admin)->get('/admin/system/backups/nope.zip')->assertNotFound();

        $this->actingAs($admin)->post('/admin/system/backups/' . $name . '/delete')
            ->assertSessionHas('status');
        $this->assertFileDoesNotExist($files[0]);
    }

    public function test_restore_from_backup_brings_back_old_data_even_when_an_old_backup_is_already_on_disk(): void
    {
        $admin = $this->admin();
        // This mirrors the old local-test failure: a stale MySQL archive was
        // selected by glob()[0] instead of the archive made by this test.
        $stale = $this->archive('backup-20000101-000000-stale-mysql.zip', 'mysql',
            "-- Road Platform backup\nCREATE TABLE `old_table` (`id` bigint unsigned NOT NULL) ENGINE=InnoDB;");
        $before = glob(storage_path('app/backups/*.zip')) ?: [];

        try {
            // known state, then back it up
            SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'color1' => '#111111']]]);
            $this->actingAs($admin)->post('/admin/system/backups')->assertSessionHas('status');
            $created = array_values(array_diff(glob(storage_path('app/backups/*.zip')) ?: [], $before));
            $this->assertCount(1, $created);
            $path = $created[0];

            // state changes after the backup
            SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'color1' => '#222222']]]);
            $this->assertSame('#222222', SiteSections::sel('ribbon', 'color1'));

            // restore the archive just made above (with confirmation)
            $upload = new UploadedFile($path, 'backup.zip', 'application/zip', null, true);
            $this->actingAs($admin)->post('/admin/system/backups/restore', [
                'backup' => $upload, 'confirm' => '1',
            ])->assertSessionHasNoErrors()->assertSessionHas('status');

            $this->assertSame('#111111', SiteSections::sel('ribbon', 'color1'));
        } finally {
            @unlink($stale);
            foreach (array_diff(glob(storage_path('app/backups/*.zip')) ?: [], $before) as $file) {
                @unlink($file);
            }
        }
    }

    public function test_restore_rejects_a_backup_from_another_database_engine_before_changing_data(): void
    {
        $admin = $this->admin();
        $current = DB::connection()->getDriverName();
        $other = $current === 'sqlite' ? 'mysql' : 'sqlite';
        $path = $this->archive('backup-20000101-000001-other-engine.zip', $other,
            "-- Road Platform backup\nDROP TABLE IF EXISTS settings;");

        try {
            SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'color1' => '#333333']]]);
            $upload = new UploadedFile($path, 'backup.zip', 'application/zip', null, true);
            $this->actingAs($admin)->post('/admin/system/backups/restore', [
                'backup' => $upload, 'confirm' => '1',
            ])->assertSessionHasErrors('backup');

            $this->assertSame('#333333', SiteSections::sel('ribbon', 'color1'));
        } finally {
            @unlink($path);
        }
    }

    public function test_failed_sql_restore_reenables_sqlite_foreign_key_checks(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('This assertion applies to SQLite foreign-key pragmas only.');
        }

        DB::unprepared('PRAGMA foreign_keys=ON');
        $path = $this->archive('backup-20000101-000002-bad-sql.zip', 'sqlite',
            "-- Road Platform backup\nTHIS IS NOT VALID SQL;");

        try {
            $upload = new UploadedFile($path, 'backup.zip', 'application/zip', null, true);
            $this->actingAs($this->admin())->post('/admin/system/backups/restore', [
                'backup' => $upload, 'confirm' => '1',
            ])->assertSessionHasErrors('backup');

            $foreignKeys = DB::selectOne('PRAGMA foreign_keys')->foreign_keys ?? 0;
            $this->assertSame(1, (int) $foreignKeys);
        } finally {
            @unlink($path);
        }
    }

    public function test_restore_needs_confirmation_and_a_valid_backup(): void
    {
        $admin = $this->admin();

        // no confirmation checkbox -> rejected
        $fake = UploadedFile::fake()->create('backup.zip', 10, 'application/zip');
        $this->actingAs($admin)->post('/admin/system/backups/restore', ['backup' => $fake])
            ->assertSessionHasErrors();

        // confirmation given but not a real site backup -> rejected
        $fake2 = UploadedFile::fake()->create('random.zip', 10, 'application/zip');
        $this->actingAs($admin)->post('/admin/system/backups/restore', ['backup' => $fake2, 'confirm' => '1'])
            ->assertSessionHasErrors();
    }

    public function test_run_database_updates_button(): void
    {
        $this->actingAs($this->admin())->post('/admin/system/migrate')
            ->assertSessionHasNoErrors()->assertSessionHas('status');
    }
}
