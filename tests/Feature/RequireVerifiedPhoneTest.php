<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequireVerifiedPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'phone'     => '+8801700000030',
            'role'      => 'citizen',
        ], $over)));
    }

    private function requireVerified(bool $on = true): void
    {
        LoginSettings::save(['require_verified_phone' => $on]);
    }

    private function plantCode(string $phone, string $code): void
    {
        \DB::table('otp_codes')->where('phone', $phone)->whereNull('used_at')->update(['used_at' => now()]);
        \DB::table('otp_codes')->insert([
            'phone' => $phone,
            'code_hash' => hash_hmac('sha256', $phone . '|' . $code, (string) config('app.key')),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ]);
    }

    public function test_default_is_off(): void
    {
        $this->assertFalse(LoginSettings::requireVerifiedPhone());

        // With the switch off an unverified user can open the report form.
        $this->actingAs($this->user())->get('/report')->assertOk();
    }

    public function test_admin_can_enable_the_toggle(): void
    {
        $admin = $this->user(['phone' => '+8801700000099', 'role' => 'admin']);
        $this->actingAs($admin)->post('/admin/login-settings', [
            'otp_enabled'            => '1',
            'otp_provider'           => 'log',
            'google_phone'           => 'optional',
            'require_verified_phone' => '1',
        ]);
        $this->assertTrue(LoginSettings::requireVerifiedPhone());

        // Off again through the same form (checkbox hidden input sends 0).
        $this->actingAs($admin)->post('/admin/login-settings', [
            'otp_enabled'            => '1',
            'otp_provider'           => 'log',
            'google_phone'           => 'optional',
            'require_verified_phone' => '0',
        ]);
        $this->assertFalse(LoginSettings::requireVerifiedPhone());
    }

    public function test_unverified_and_no_phone_users_are_gated(): void
    {
        $this->requireVerified();

        $unverified = $this->user();
        $this->actingAs($unverified)->get('/report')
            ->assertRedirect(route('account.phone'));
        $this->assertStringEndsWith('/report', (string) session('url.intended'));

        // POSTs are gated too.
        $this->actingAs($unverified)->post('/report', [])->assertRedirect(route('account.phone'));
        // Volunteer application is gated as well.
        $this->actingAs($unverified)->get('/volunteer/apply')->assertRedirect(route('account.phone'));

        $this->flushSession();
        $noPhone = $this->user(['phone' => null, 'phone_verified_at' => null]);
        $this->actingAs($noPhone)->get('/report')->assertRedirect(route('account.phone'));

        // The verification page itself must never loop.
        $this->actingAs($noPhone)->get('/account/phone')->assertOk();
    }

    public function test_verified_user_and_off_switch_are_not_gated(): void
    {
        $this->requireVerified();
        $verified = $this->user(['phone' => '+8801700000031', 'phone_verified_at' => now()]);
        $this->actingAs($verified)->get('/report')->assertOk();

        $this->requireVerified(false);
        $this->actingAs($this->user(['phone' => '+8801700000032']))->get('/report')->assertOk();
    }

    public function test_verifying_returns_to_the_intended_page(): void
    {
        $this->requireVerified();
        $u = $this->user();
        $this->actingAs($u)->get('/report')->assertRedirect(route('account.phone'));
        $intended = (string) session('url.intended');
        $this->assertStringEndsWith('/report', $intended);

        $this->plantCode('+8801700000030', '921384');
        $this->actingAs($u)->post('/account/phone/verify', ['code' => '921384'])
            ->assertRedirect($intended);
        $this->assertNotNull($u->fresh()->phone_verified_at);
    }
}
