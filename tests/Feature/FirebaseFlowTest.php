<?php

namespace Tests\Feature;

use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FirebaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private function useFirebase(): void
    {
        LoginSettings::save([
            'otp_provider' => 'firebase',
            'firebase_api_key' => 'k',
            'firebase_auth_domain' => 'a.firebaseapp.com',
            'firebase_project_id' => 'p',
        ]);
    }

    public function test_login_with_firebase_sends_no_server_code(): void
    {
        $this->useFirebase();
        $this->post('/login', ['phone' => '01712345678'])
            ->assertRedirect(route('login.verify'))
            ->assertSessionHas('otp_phone', '+8801712345678');
        $this->assertSame(0, DB::table('otp_codes')->count());
    }

    public function test_verify_page_shows_firebase_form(): void
    {
        $this->useFirebase();
        $this->withSession(['otp_phone' => '+8801712345678'])
            ->get('/login/verify')
            ->assertOk()
            ->assertSee('id="firebase-login"', false)
            ->assertSee('data-api-key="k"', false)
            ->assertSee('data-phone="+8801712345678"', false);
    }

    public function test_bangla_strings_are_real_utf8(): void
    {
        app()->setLocale('bn');
        $t = __('loginsettings.fbl_send');
        $this->assertNotSame('loginsettings.fbl_send', $t);
        $this->assertTrue(mb_check_encoding($t, 'UTF-8'));
        $this->assertSame(1, preg_match('/\p{Bengali}/u', $t));
        app()->setLocale('en');
        $this->assertSame('Send code', __('loginsettings.fbl_send'));
    }
}