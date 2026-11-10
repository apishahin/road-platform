<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private const LOCAL = '01712345678';
    private const PHONE = '+8801712345678';
    private const PW = 'longpassword1';

    private function setting(array $over): void
    {
        LoginSettings::save($over);
    }

    private function makeUser(string $role = 'citizen', ?string $password = self::PW, bool $verified = false, bool $banned = false): User
    {
        $u = new User(['phone' => self::PHONE, 'display_name' => 'User', 'role' => $role]);
        $u->forceFill([
            'password'          => $password,
            'is_banned'         => $banned,
            'phone_verified_at' => $verified ? now() : null,
        ])->save();

        return $u;
    }

    private function putOtp(string $code): void
    {
        DB::table('otp_codes')->insert([
            'phone'      => self::PHONE,
            'code_hash'  => hash_hmac('sha256', self::PHONE . '|' . $code, (string) config('app.key')),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(5),
            'used_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pwPost(string $password): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login/password', ['phone' => self::LOCAL, 'password' => $password]);
    }

    public function test_default_page_shows_only_otp_form(): void
    {
        $this->get('/login')->assertOk()
            ->assertDontSee(route('login.password'))
            ->assertDontSee(route('login.register'));
    }

    public function test_page_shows_password_forms_when_enabled(): void
    {
        $this->setting(['password_enabled' => true]);

        $this->get('/login')->assertOk()
            ->assertSee(route('login.password'))
            ->assertSee(route('login.register'));
    }

    public function test_password_login_refused_when_switch_off(): void
    {
        $this->makeUser();

        $this->pwPost(self::PW)->assertSessionHasErrors('pwlogin');
        $this->assertGuest();
    }

    public function test_password_login_works_and_wrong_password_fails(): void
    {
        $this->setting(['password_enabled' => true]);
        $user = $this->makeUser();

        $this->pwPost('wrong-password')->assertSessionHasErrors('pwlogin');
        $this->assertGuest();

        $this->pwPost(self::PW)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_banned_user_cannot_use_password(): void
    {
        $this->setting(['password_enabled' => true]);
        $this->makeUser('citizen', self::PW, false, true);

        $this->pwPost(self::PW)->assertSessionHasErrors('pwlogin');
        $this->assertGuest();
    }

    public function test_staff_password_login_needs_staff_switch(): void
    {
        $this->setting(['password_enabled' => true, 'password_for_staff' => false]);
        $user = $this->makeUser('volunteer');

        $this->pwPost(self::PW)->assertSessionHasErrors('pwlogin');
        $this->assertGuest();

        $this->setting(['password_for_staff' => true]);
        $this->pwPost(self::PW)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_creates_unverified_account(): void
    {
        $this->setting(['password_enabled' => true]);

        $this->post('/login/register', [
            'phone' => self::LOCAL, 'password' => self::PW, 'password_confirmation' => self::PW,
        ])->assertSessionHasNoErrors();

        $user = User::where('phone', self::PHONE)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('citizen', $user->role);
        $this->assertNotSame(self::PW, $user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_refused_for_existing_phone(): void
    {
        $this->setting(['password_enabled' => true]);
        $this->makeUser();

        $this->post('/login/register', [
            'phone' => self::LOCAL, 'password' => 'anotherpassword9', 'password_confirmation' => 'anotherpassword9',
        ])->assertSessionHasErrors('register');

        $this->assertSame(1, User::where('phone', self::PHONE)->count());
        $this->assertGuest();
    }

    public function test_register_refused_when_registration_closed(): void
    {
        $this->setting(['password_enabled' => true, 'registration_open' => false]);

        $this->post('/login/register', [
            'phone' => self::LOCAL, 'password' => self::PW, 'password_confirmation' => self::PW,
        ])->assertSessionHasErrors('register');

        $this->assertNull(User::where('phone', self::PHONE)->first());
    }

    public function test_register_refused_for_short_or_mismatched_password(): void
    {
        $this->setting(['password_enabled' => true]);

        $this->post('/login/register', [
            'phone' => self::LOCAL, 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('register');

        $this->post('/login/register', [
            'phone' => self::LOCAL, 'password' => self::PW, 'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('register');

        $this->assertNull(User::where('phone', self::PHONE)->first());
    }

    public function test_otp_login_clears_password_of_unverified_account(): void
    {
        $this->makeUser();
        $this->putOtp('123456');

        $this->withSession(['otp_phone' => self::PHONE])
            ->post('/login/verify', ['code' => '123456'])
            ->assertSessionHasNoErrors();

        $user = User::where('phone', self::PHONE)->first();
        $this->assertNull($user->password);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_otp_login_keeps_password_of_verified_account(): void
    {
        $this->makeUser('citizen', self::PW, true);
        $this->putOtp('123456');

        $this->withSession(['otp_phone' => self::PHONE])
            ->post('/login/verify', ['code' => '123456'])
            ->assertSessionHasNoErrors();

        $user = User::where('phone', self::PHONE)->first();
        $this->assertNotNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_otp_off_refuses_code_request(): void
    {
        $this->setting(['otp_enabled' => false, 'password_enabled' => true]);

        $this->post('/login', ['phone' => self::LOCAL])->assertSessionHasErrors('phone');
        $this->assertSame(0, DB::table('otp_codes')->count());
    }

    public function test_otp_new_account_refused_when_registration_closed(): void
    {
        $this->setting(['registration_open' => false]);

        $this->post('/login', ['phone' => self::LOCAL])->assertSessionHasErrors('phone');
        $this->assertSame(0, DB::table('otp_codes')->count());
    }

    public function test_otp_existing_user_ok_when_registration_closed(): void
    {
        $this->setting(['registration_open' => false]);
        $this->makeUser();

        $this->post('/login', ['phone' => self::LOCAL])->assertRedirect(route('login.verify'));
    }

    public function test_admin_cannot_turn_otp_off_without_own_password(): void
    {
        $admin = new User(['phone' => '+8801799999999', 'display_name' => 'A', 'role' => 'admin']);
        $admin->save();

        $payload = [
            'otp_enabled'        => '0',
            'password_enabled'   => '1',
            'google_enabled'     => '0',
            'registration_open'  => '1',
            'password_for_staff' => '1',
            'otp_provider'       => 'log',
        ];

        $this->actingAs($admin)->post('/admin/login-settings', $payload)->assertSessionHasErrors('methods');
        $this->assertTrue(LoginSettings::otpEnabled());

        $admin->forceFill(['password' => self::PW])->save();
        $this->actingAs($admin)->post('/admin/login-settings', $payload)->assertSessionHasNoErrors();
        $this->assertFalse(LoginSettings::otpEnabled());
    }
}