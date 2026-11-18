<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_restore_from_backup_brings_back_old_data(): void
    {
        $admin = $this->admin();

        // known state, then back it up
        SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'color1' => '#111111']]]);
        $this->actingAs($admin)->post('/admin/system/backups')->assertSessionHas('status');
        $path = glob(storage_path('app/backups/*.zip'))[0];

        // state changes after the backup
        SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'color1' => '#222222']]]);
        $this->assertSame('#222222', SiteSections::sel('ribbon', 'color1'));

        // restore the uploaded backup (with the required confirmation)
        $upload = new UploadedFile($path, 'backup.zip', 'application/zip', null, true);
        $this->actingAs($admin)->post('/admin/system/backups/restore', [
            'backup' => $upload, 'confirm' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame('#111111', SiteSections::sel('ribbon', 'color1'));
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
