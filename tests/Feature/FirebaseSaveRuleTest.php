<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirebaseSaveRuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['phone' => '+8801900000001', 'display_name' => 'A', 'role' => 'admin']);
    }

    private function save(array $over)
    {
        return $this->actingAs($this->admin())->post('/admin/login-settings', array_merge([
            'otp_enabled' => '1',
            'otp_provider' => 'firebase',
            'firebase_api_key' => 'key',
            'firebase_auth_domain' => 'a.firebaseapp.com',
            'firebase_project_id' => 'proj',
        ], $over));
    }

    public function test_firebase_with_empty_config_is_rejected(): void
    {
        $this->save(['firebase_api_key' => '', 'firebase_auth_domain' => '', 'firebase_project_id' => ''])
            ->assertSessionHasErrors('methods');
        $this->assertSame('log', LoginSettings::provider());
    }

    public function test_firebase_with_one_missing_value_is_rejected(): void
    {
        $this->save(['firebase_project_id' => '  '])->assertSessionHasErrors('methods');
        $this->assertSame('log', LoginSettings::provider());
    }

    public function test_other_provider_saves_with_empty_firebase_fields(): void
    {
        $this->save(['otp_provider' => 'log', 'firebase_api_key' => '', 'firebase_auth_domain' => '', 'firebase_project_id' => ''])
            ->assertSessionHasNoErrors()
            ->assertStatus(302);
        $this->assertSame('log', LoginSettings::provider());
    }
}