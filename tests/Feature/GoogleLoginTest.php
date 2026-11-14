<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone, array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'phone'     => $phone,
            'role'      => $role,
        ], $over)));
    }

    private function enableGoogle(): void
    {
        LoginSettings::save([
            'google_enabled' => true,
            'firebase_api_key' => 'AIza-test-key',
            'firebase_auth_domain' => 'x.firebaseapp.com',
            'firebase_project_id' => 'project-x',
        ]);
    }

    private function token(array $over = []): string
    {
        $payload = array_merge([
            'auth_time' => time(),
            'firebase'  => ['sign_in_provider' => 'google.com'],
        ], $over);
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        return "aaa.{$p}.ccc";
    }

    private function fakeLookup(array $resp): void
    {
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response($resp, 200)]);
    }

    public function test_google_routes_404_when_off(): void
    {
        $this->get('/login/google')->assertNotFound();
        $this->postJson('/login/google/verify', ['id_token' => 'x'])->assertNotFound();
    }

    public function test_google_start_page_renders_when_on(): void
    {
        $this->enableGoogle();
        $this->get('/login/google')->assertOk()->assertSee('google-login');
    }

    public function test_existing_google_user_is_logged_in_with_remember(): void
    {
        $this->enableGoogle();
        $user = $this->makeUser('citizen', '01700000021', ['google_id' => 'gid-1', 'phone_verified_at' => now()]);
        $this->fakeLookup(['users' => [['localId' => 'gid-1', 'email' => 'a@b.c', 'displayName' => 'A']]]);

        $resp = $this->postJson('/login/google/verify', ['id_token' => $this->token()]);
        $resp->assertOk()->assertJson(['ok' => true]);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_new_user_goes_to_phone_step(): void
    {
        $this->enableGoogle();
        $this->fakeLookup(['users' => [['localId' => 'gid-new', 'email' => 'n@b.c', 'displayName' => 'New']]]);

        $resp = $this->postJson('/login/google/verify', ['id_token' => $this->token()]);
        $resp->assertOk()->assertJson(['ok' => true]);
        $resp->assertJson(['next' => route('login.google.phone')]);
        $this->assertGuest();
    }

    public function test_phone_step_creates_user_and_logs_in(): void
    {
        $this->enableGoogle();

        $this->withSession(['google_pending' => ['id' => 'gid-2', 'email' => 'p@b.c', 'name' => 'P']])
            ->post('/login/google/phone', ['phone' => '01700000022'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('otp_codes', ['phone' => '+8801700000022', 'used_at' => null]);

        // Wrong code: stays guest.
        $this->withSession(['google_pending' => ['id' => 'gid-2', 'email' => 'p@b.c', 'name' => 'P'], 'google_phone' => '+8801700000022'])
            ->post('/login/google/phone/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // Now plant a known code with the same HMAC the service uses.
        $otp = \App\Services\OtpService::class;
        $phone = '+8801700000022';
        $code  = '123456';
        \DB::table('otp_codes')->where('phone', $phone)->whereNull('used_at')->update(['used_at' => now()]);
        \DB::table('otp_codes')->insert([
            'phone' => $phone,
            'code_hash' => hash_hmac('sha256', $phone . '|' . $code, (string) config('app.key')),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ]);

        $this->withSession(['google_pending' => ['id' => 'gid-2', 'email' => 'p@b.c', 'name' => 'P'], 'google_phone' => $phone])
            ->post('/login/google/phone/verify', ['code' => $code])
            ->assertRedirect(route('home'));

        $user = User::where('phone', $phone)->first();
        $this->assertNotNull($user);
        $this->assertSame('gid-2', $user->google_id);
        $this->assertSame('p@b.c', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_phone_step_without_pending_google_404(): void
    {
        $this->enableGoogle();
        $this->get('/login/google/phone')->assertNotFound();
    }

    public function test_google_login_button_on_login_page(): void
    {
        LoginSettings::save(['google_enabled' => true]);
        $this->get('/login')->assertOk()->assertSee(route('login.google'), false);
    }
}
