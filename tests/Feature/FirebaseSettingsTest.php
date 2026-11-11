<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FirebaseSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['phone' => '+8801790000001', 'display_name' => 'A', 'role' => 'admin']);
    }

    public function test_defaults_have_firebase_keys(): void
    {
        $d = LoginSettings::defaults();
        $this->assertSame('', $d['firebase_api_key']);
        $this->assertSame('', $d['firebase_auth_domain']);
        $this->assertSame('', $d['firebase_project_id']);
    }

    public function test_admin_can_save_firebase_settings(): void
    {
        $a = $this->admin();
        $this->actingAs($a)->post('/admin/login-settings', [
            'otp_enabled' => '1',
            'otp_provider' => 'firebase',
            'firebase_api_key' => 'AIzaX',
            'firebase_auth_domain' => 'road.firebaseapp.com',
            'firebase_project_id' => 'road',
        ])->assertStatus(302);

        $raw = DB::table('settings')->where('key', 'login_settings')->value('value');
        $this->assertStringContainsString('AIzaX', (string) $raw);
        $this->assertStringContainsString('road.firebaseapp.com', (string) $raw);
    }

    public function test_firebase_option_selectable_and_fields_shown(): void
    {
        $a = $this->admin();
        $resp = $this->actingAs($a)->get('/admin/login-settings');
        $resp->assertOk();
        $html = $resp->getContent();
        $this->assertDoesNotMatchRegularExpression('/value="firebase"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/value="telegram"[^>]*disabled/', $html);
        $this->assertStringContainsString('name="firebase_api_key"', $html);
        $this->assertStringContainsString('name="firebase_auth_domain"', $html);
        $this->assertStringContainsString('name="firebase_project_id"', $html);
    }
}