<?php

namespace Tests\Feature;

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
            'phone' => '0181' . random_int(1000000, 9999999),
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

    public function test_non_admin_cannot_change_r2_settings_and_required_credentials_are_validated(): void
    {
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
