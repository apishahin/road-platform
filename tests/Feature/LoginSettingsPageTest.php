<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => $phone,
            'role'      => $role,
        ]));
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'otp_enabled'        => '1',
            'password_enabled'   => '0',
            'google_enabled'     => '0',
            'registration_open'  => '1',
            'password_for_staff' => '0',
            'otp_provider'       => 'log',
        ], $over);
    }

    public function test_guest_gets_403(): void
    {
        $this->get('/admin/login-settings')->assertForbidden();
        $this->post('/admin/login-settings', $this->payload())->assertForbidden();
    }

    public function test_citizen_and_moderator_get_403(): void
    {
        $citizen = $this->makeUser('citizen', '01700000011');
        $mod = $this->makeUser('moderator', '01700000012');

        $this->actingAs($citizen)->get('/admin/login-settings')->assertForbidden();
        $this->actingAs($mod)->get('/admin/login-settings')->assertForbidden();
        $this->actingAs($mod)->post('/admin/login-settings', $this->payload(['password_enabled' => '1']))->assertForbidden();
        $this->assertFalse(LoginSettings::passwordEnabled());
    }

    public function test_admin_can_open_page(): void
    {
        $admin = $this->makeUser('admin', '01700000013');

        $this->actingAs($admin)->get('/admin/login-settings')
            ->assertOk()
            ->assertSee(__('loginsettings.title'));
    }

    public function test_admin_can_save_and_google_settings_keep_persist(): void
    {
        $admin = $this->makeUser('admin', '01700000014');

        $this->actingAs($admin)
            ->post('/admin/login-settings', $this->payload([
                'password_enabled' => '1',
                'google_enabled'   => '1',
                'firebase_api_key' => 'AIza-test-key',
                'firebase_auth_domain' => 'x.firebaseapp.com',
                'firebase_project_id' => 'project-x',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(LoginSettings::passwordEnabled());
        $this->assertTrue(LoginSettings::googleEnabled());
        $this->assertDatabaseHas('audit_events', ['action' => 'login_settings.save']);
    }

    public function test_google_requires_firebase_config(): void
    {
        $admin = $this->makeUser('admin', '01700000016');

        $this->actingAs($admin)
            ->post('/admin/login-settings', $this->payload([
                'google_enabled' => '1',
                // firebase fields are empty in the payload
            ]))
            ->assertSessionHasErrors('methods');

        $this->assertFalse(LoginSettings::googleEnabled());
        $this->assertDatabaseMissing('audit_events', ['action' => 'login_settings.save']);
    }

    public function test_cannot_turn_off_every_login_method(): void
    {
        $admin = $this->makeUser('admin', '01700000015');

        $this->actingAs($admin)
            ->post('/admin/login-settings', $this->payload([
                'otp_enabled'      => '0',
                'password_enabled' => '0',
            ]))
            ->assertSessionHasErrors('methods');

        $this->assertTrue(LoginSettings::otpEnabled());
        $this->assertDatabaseMissing('audit_events', ['action' => 'login_settings.save']);
    }
}