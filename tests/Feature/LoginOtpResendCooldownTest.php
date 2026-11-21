<?php

namespace Tests\Feature;

use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginOtpResendCooldownTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '01712345678';

    public function test_admin_configured_wait_is_rendered_and_enforced_for_gateway_login_otp(): void
    {
        LoginSettings::save(['otp_resend_seconds' => 75]);

        $this->post('/login', ['phone' => self::PHONE])
            ->assertRedirect(route('login.verify'));

        $this->get('/login/verify')->assertOk()
            ->assertSee('data-seconds="75"', false)
            ->assertSee('data-template="' . __('login.resend_wait_seconds', ['seconds' => '__seconds__']) . '"', false);

        $this->post('/login/resend')
            ->assertRedirect(route('login.verify'))
            ->assertSessionHas('otp_resend_notice');

        $this->get('/login/verify')->assertOk()
            ->assertSee('data-resend-hidden', false)
            ->assertSee(__('login.resend_wait_title'));

        // A blocked resend cannot replace the existing OTP row.
        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_repeated_gateway_resend_posts_stay_on_a_friendly_cooldown_flow_not_a_429_page(): void
    {
        LoginSettings::save(['otp_resend_seconds' => 60]);
        $this->post('/login', ['phone' => self::PHONE])->assertRedirect(route('login.verify'));

        for ($attempt = 0; $attempt < 7; $attempt++) {
            $this->post('/login/resend')
                ->assertRedirect(route('login.verify'))
                ->assertSessionHas('otp_resend_notice');
        }

        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_firebase_login_page_receives_the_same_admin_configured_wait(): void
    {
        LoginSettings::save([
            'otp_provider' => 'firebase',
            'otp_resend_seconds' => 75,
        ]);

        $this->post('/login', ['phone' => self::PHONE])
            ->assertRedirect(route('login.verify'));

        $this->get('/login/verify')->assertOk()
            ->assertSee('data-resend-wait-seconds="75"', false);
    }
}
