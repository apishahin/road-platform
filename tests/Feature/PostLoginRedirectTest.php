<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'role'      => 'citizen',
            'phone_verified_at' => now(),
        ], $over)));
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

    private function loginWithOtp(string $phone, string $code): \Illuminate\Testing\TestResponse
    {
        $this->post('/login', ['phone' => $phone]);
        $this->plantCode(\App\Support\Phone::normalize($phone), $code);

        return $this->post('/login/verify', ['code' => $code]);
    }

    public function test_admin_is_landed_on_the_dashboard(): void
    {
        $this->user(['phone' => '+8801700000099', 'role' => 'admin']);
        $this->loginWithOtp('01700000099', '432101')->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_moderator_is_landed_on_the_dashboard(): void
    {
        $this->user(['phone' => '+8801700000090', 'role' => 'moderator']);
        $this->loginWithOtp('01700000090', '432102')->assertRedirect(route('admin.dashboard'));
    }

    public function test_citizen_goes_home_or_back_to_intended_page(): void
    {
        // Plain citizen: home.
        $this->user(['phone' => '+8801700000030']);
        $this->loginWithOtp('01700000030', '432103')->assertRedirect(route('home'));

        \Auth::logout();

        // Citizen bounced from /report first: back there after login.
        $this->get('/report')->assertRedirect(route('login'));
        $intended = (string) session('url.intended');
        $this->assertStringEndsWith('/report', $intended);
        $this->loginWithOtp('01700000030', '432104')->assertRedirect($intended);
    }

    public function test_staff_ignore_intended_and_see_dashboard_first(): void
    {
        $this->user(['phone' => '+8801700000099', 'role' => 'admin']);
        $this->get('/report'); // plants url.intended
        $this->loginWithOtp('01700000099', '432105')->assertRedirect(route('admin.dashboard'));
    }

    public function test_moderator_sees_dashboard_link_in_staff_menu(): void
    {
        $mod = $this->user(['phone' => '+8801700000090', 'role' => 'moderator']);
        $this->actingAs($mod)->get('/')->assertOk()->assertSee(route('admin.dashboard'));
    }
}
