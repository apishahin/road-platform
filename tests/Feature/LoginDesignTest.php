<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppearanceSettings;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginDesignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone' => '+8801799999999',
            'role' => 'admin',
        ]));
    }

    public function test_soft_design_is_the_safe_default(): void
    {
        $this->assertSame('soft', LoginSettings::design());
    }

    public function test_every_admin_selectable_design_renders_on_login_page(): void
    {
        foreach (LoginSettings::DESIGNS as $design) {
            LoginSettings::save(['login_design' => $design]);

            $this->get('/login')->assertOk()
                ->assertSee('data-login-design="' . $design . '"', false);
        }
    }

    public function test_login_reuses_the_admin_uploaded_site_logo(): void
    {
        AppearanceSettings::save([
            'name_bn' => 'আমার সাইট',
            'logo' => 'logo-abcdefghijklmnop.png',
        ]);

        $this->get('/login')->assertOk()
            ->assertSee('uploads/appearance/logo-abcdefghijklmnop.png', false)
            ->assertDontSee('login-brand-fallback', false);
    }

    public function test_admin_can_save_the_chosen_login_design(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/login-settings', [
            'otp_enabled' => '1',
            'password_enabled' => '0',
            'google_enabled' => '0',
            'registration_open' => '1',
            'password_for_staff' => '0',
            'require_verified_phone' => '0',
            'otp_provider' => 'gateway',
            'google_phone' => 'optional',
            'login_design' => 'split',
            'firebase_api_key' => '',
            'firebase_auth_domain' => '',
            'firebase_project_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('split', LoginSettings::design());
        $this->actingAs($admin)->get('/admin/login-settings')
            ->assertOk()
            ->assertSee('name="login_design"', false)
            ->assertSee(__('loginsettings.design_split'));
    }

    public function test_bad_design_is_rejected_and_never_saved(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/login-settings', [
            'otp_enabled' => '1',
            'otp_provider' => 'log',
            'google_phone' => 'optional',
            'login_design' => 'not-a-design',
        ])->assertSessionHasErrors('login_design');

        $this->assertSame('soft', LoginSettings::design());
    }
}
