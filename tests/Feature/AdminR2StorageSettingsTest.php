<?php

namespace Tests\Feature;

use App\Models\Evidence;
use App\Models\User;
use App\Support\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminR2StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone' => '0181'.random_int(1000000, 9999999),
            'role' => $role,
        ]));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'r2_endpoint' => 'https://abc123.r2.cloudflarestorage.com',
            'r2_private_bucket' => 'road-private',
            'r2_public_bucket' => 'road-public',
            'r2_public_url' => 'https://images.example.test',
            'r2_access_key_id' => 'access-key-id-for-test',
            'r2_secret_access_key' => 'secret-key-for-test',
        ], $overrides);
    }

    public function test_admin_can_save_encrypted_r2_configuration_without_exposing_credentials(): void
    {
        $admin = $this->user();

        $this->actingAs($admin)
            ->post(route('admin.storage.r2'), $this->payload())
            ->assertRedirect(route('admin.storage.index'))
            ->assertSessionHasNoErrors();

        $stored = (string) DB::table('settings')->where('key', StorageSettings::R2_KEY)->value('value');
        $this->assertNotSame('', $stored);
        $this->assertStringNotContainsString('access-key-id-for-test', $stored);
        $this->assertStringNotContainsString('secret-key-for-test', $stored);

        $r2 = StorageSettings::r2();
        $this->assertSame('https://abc123.r2.cloudflarestorage.com', $r2['endpoint']);
        $this->assertSame('road-private', $r2['private_bucket']);
        $this->assertSame('road-public', $r2['public_bucket']);
        $this->assertSame('https://images.example.test', $r2['public_url']);
        $this->assertSame('access-key-id-for-test', $r2['access_key_id']);
        $this->assertSame('secret-key-for-test', $r2['secret_access_key']);
        $this->assertTrue(StorageSettings::r2Configured());

        StorageSettings::apply();
        $this->assertSame('road-private', config('filesystems.disks.r2_private.bucket'));
        $this->assertSame('road-public', config('filesystems.disks.r2_public.bucket'));
        $this->assertSame('access-key-id-for-test', config('filesystems.disks.r2_private.key'));
        $this->assertSame('secret-key-for-test', config('filesystems.disks.r2_public.secret'));

        $this->actingAs($admin)->get(route('admin.storage.index'))
            ->assertOk()
            ->assertSee('Cloudflare R2 সংযোগ সেটআপ')
            ->assertDontSee('access-key-id-for-test')
            ->assertDontSee('secret-key-for-test');
    }

    public function test_admin_can_test_then_select_database_configured_r2(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post(route('admin.storage.r2'), $this->payload())
            ->assertSessionHasNoErrors();

        Storage::fake('r2_private');
        Storage::fake('r2_public');

        $this->actingAs($admin)->post(route('admin.storage.check'), ['target' => 'r2'])
            ->assertRedirect(route('admin.storage.index'))
            ->assertSessionHas('checks');

        $this->actingAs($admin)->post(route('admin.storage.update'), ['storage' => 'r2'])
            ->assertRedirect(route('admin.storage.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame('r2', StorageSettings::choice());
    }

    public function test_blank_credential_fields_keep_the_existing_encrypted_values(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post(route('admin.storage.r2'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.storage.r2'), $this->payload([
            'r2_public_url' => 'https://cdn.example.test',
            'r2_access_key_id' => '',
            'r2_secret_access_key' => '',
        ]))->assertSessionHasNoErrors();

        $r2 = StorageSettings::r2();
        $this->assertSame('https://cdn.example.test', $r2['public_url']);
        $this->assertSame('access-key-id-for-test', $r2['access_key_id']);
        $this->assertSame('secret-key-for-test', $r2['secret_access_key']);
    }

    private function configureWorkingR2Fakes(): void
    {
        StorageSettings::saveR2([
            'endpoint' => 'https://abc123.r2.cloudflarestorage.com',
            'private_bucket' => 'road-private',
            'public_bucket' => 'road-public',
            'public_url' => 'https://images.example.test',
        ], 'access-key-id-for-test', 'secret-key-for-test');
        StorageSettings::apply();

        foreach (['private', 'public', 'r2_private', 'r2_public'] as $disk) {
            Storage::fake($disk);
        }
    }

    public function test_admin_can_copy_all_evidence_types_then_switch_storage_without_losing_old_files(): void
    {
        $admin = $this->user();
        $this->configureWorkingR2Fakes();

        $pending = Evidence::unguarded(fn () => Evidence::create([
            'uploader_id' => $admin->id,
            'private_path' => 'evidence/originals/pending-original.jpg',
            'public_path' => 'evidence/pending/pending-processed.jpg',
            'taken_at' => now(),
            'moderation' => 'pending',
        ]));
        $hidden = Evidence::unguarded(fn () => Evidence::create([
            'uploader_id' => $admin->id,
            'private_path' => null,
            'public_path' => 'evidence/pending/hidden-processed.jpg',
            'taken_at' => now(),
            'moderation' => 'hidden',
        ]));
        $approved = Evidence::unguarded(fn () => Evidence::create([
            'uploader_id' => $admin->id,
            'private_path' => 'evidence/originals/approved-original.jpg',
            'public_path' => 'evidence/public/approved-processed.jpg',
            'taken_at' => now(),
            'moderation' => 'approved',
        ]));

        Storage::disk('private')->put($pending->private_path, 'pending original');
        Storage::disk('private')->put($pending->public_path, 'pending processed');
        Storage::disk('private')->put($hidden->public_path, 'hidden processed');
        Storage::disk('private')->put($approved->private_path, 'approved original');
        Storage::disk('public')->put($approved->public_path, 'approved processed');

        // The storage choice itself remains protected until the row-level
        // references have moved. This replaces the old "finish moderation"
        // dead end with an Admin-run migration path.
        $this->actingAs($admin)->post(route('admin.storage.update'), ['storage' => 'r2'])
            ->assertSessionHasErrors('storage');
        $this->assertSame('local', StorageSettings::choice());

        $this->actingAs($admin)->post(route('admin.storage.migrate'), [
            'storage' => 'r2',
            'limit' => 25,
        ])->assertRedirect(route('admin.storage.index'))
            ->assertSessionHas('migration', fn (array $result) => $result['migrated'] === 3
                && $result['failed'] === 0 && $result['remaining'] === 0);

        $pending->refresh();
        $hidden->refresh();
        $approved->refresh();
        $this->assertSame('r2_private', $pending->private_disk);
        $this->assertNull($pending->public_disk);
        $this->assertSame('r2_private', $hidden->private_disk);
        $this->assertNull($hidden->public_disk);
        $this->assertSame('r2_private', $approved->private_disk);
        $this->assertSame('r2_public', $approved->public_disk);

        Storage::disk('r2_private')->assertExists([$pending->private_path, $pending->public_path, $hidden->public_path, $approved->private_path]);
        Storage::disk('r2_public')->assertExists($approved->public_path);
        // Copy, rather than delete, keeps a verified rollback/fallback copy.
        Storage::disk('private')->assertExists([$pending->private_path, $pending->public_path, $hidden->public_path, $approved->private_path]);
        Storage::disk('public')->assertExists($approved->public_path);

        $this->actingAs($admin)->post(route('admin.storage.update'), ['storage' => 'r2'])
            ->assertRedirect(route('admin.storage.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame('r2', StorageSettings::choice());
    }

    public function test_missing_legacy_file_is_reported_and_its_pointer_is_not_changed(): void
    {
        $admin = $this->user();
        $this->configureWorkingR2Fakes();

        $id = DB::table('evidence')->insertGetId([
            'uploader_id' => $admin->id,
            'private_path' => null,
            'public_path' => 'evidence/pending/missing.jpg',
            'taken_at' => now(),
            'moderation' => 'pending',
            'private_disk' => null,
            'public_disk' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.storage.migrate'), [
            'storage' => 'r2',
            'limit' => 10,
        ])->assertRedirect(route('admin.storage.index'))
            ->assertSessionHas('migration', fn (array $result) => $result['failed'] === 1
                && $result['remaining'] === 1
                && ($result['issues'][0]['evidence_id'] ?? null) === $id);

        $row = DB::table('evidence')->find($id);
        $this->assertNull($row->private_disk);
        Storage::disk('r2_private')->assertMissing('evidence/pending/missing.jpg');
    }

    public function test_non_admin_cannot_change_r2_settings_and_required_credentials_are_validated(): void
    {
        // The test verifies a brand-new setup, regardless of any developer
        // R2 credentials supplied through a local .env file.
        config([
            'filesystems.disks.r2_private.key' => null,
            'filesystems.disks.r2_private.secret' => null,
            'filesystems.disks.r2_public.key' => null,
            'filesystems.disks.r2_public.secret' => null,
        ]);

        $citizen = $this->user('citizen');
        $this->actingAs($citizen)->post(route('admin.storage.r2'), $this->payload())
            ->assertForbidden();

        $admin = $this->user();
        $this->actingAs($admin)->post(route('admin.storage.r2'), $this->payload([
            'r2_access_key_id' => '',
            'r2_secret_access_key' => '',
        ]))->assertSessionHasErrors('r2_access_key_id');
    }
}
