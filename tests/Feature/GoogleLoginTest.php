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

    private function enableGoogle(array $over = []): void
    {
        LoginSettings::save(array_merge([
            'google_enabled' => true,
            'firebase_api_key' => 'AIza-test-key',
            'firebase_auth_domain' => 'x.firebaseapp.com',
            'firebase_project_id' => 'project-x',
        ], $over));
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

    public function test_old_google_start_url_redirects_to_the_single_login_page(): void
    {
        $this->enableGoogle();
        $this->get('/login/google')->assertRedirect(route('login'));
    }

    public function test_login_page_has_google_button_and_its_verification_endpoint(): void
    {
        $this->enableGoogle();
        $this->get('/login')->assertOk()
            ->assertSee('id="google-login"', false)
            ->assertSee('id="gl-btn"', false)
            ->assertSee(route('login.google.verify'), false)
            ->assertSee('data-text-domain=', false)
            ->assertSee('data-text-provider=', false)
            ->assertSee('data-text-popup-blocked=', false)
            ->assertSee('data-text-verification-failed=', false);
    }

    public function test_existing_google_user_is_logged_in_with_remember(): void
    {
        $this->enableGoogle();
        $user = $this->makeUser('citizen', '01700000021', ['google_id' => 'gid-1', 'phone_verified_at' => now()]);
        $this->fakeLookup(['users' => [['localId' => 'gid-1', 'email' => 'a@b.c', 'displayName' => 'A']]]);

        $resp = $this->postJson('/login/google/verify', ['id_token' => $this->token()]);
        $resp->assertOk()->assertJson(['ok' => true, 'next' => route('home')]);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_new_google_user_is_logged_in_in_one_tap_by_default(): void
    {
        $this->enableGoogle();
        $this->fakeLookup(['users' => [['localId' => 'gid-new', 'email' => 'n@b.c', 'displayName' => 'New']]]);

        $resp = $this->postJson('/login/google/verify', ['id_token' => $this->token()]);
        $resp->assertOk()->assertJson(['ok' => true, 'next' => route('home')]);
        $this->assertAuthenticated();
        $user = User::where('google_id', 'gid-new')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->phone);
        $this->assertNull(session('google_pending'));
    }

    public function test_new_google_user_goes_to_phone_step_when_admin_requires_it(): void
    {
        $this->enableGoogle(['google_phone' => 'required']);
        $this->fakeLookup(['users' => [['localId' => 'gid-required', 'email' => 'r@b.c', 'displayName' => 'Required']]]);

        $resp = $this->postJson('/login/google/verify', ['id_token' => $this->token()]);
        $resp->assertOk()->assertJson(['ok' => true, 'next' => route('login.google.phone')]);
        $this->assertGuest();
    }

    public function test_phone_is_saved_without_otp_and_user_is_logged_in(): void
    {
        $this->enableGoogle();
        $pending = ['google_pending' => ['id' => 'gid-2', 'email' => 'p@b.c', 'name' => 'P']];

        // Invalid number refused.
        $this->withSession($pending)
            ->post('/login/google/phone', ['phone' => '123'])
            ->assertSessionHasErrors('phone');
        $this->assertGuest();

        $this->withSession($pending)
            ->post('/login/google/phone', ['phone' => '01700000022'])
            ->assertRedirect(route('home'));

        $user = User::where('phone', '+8801700000022')->first();
        $this->assertNotNull($user);
        $this->assertSame('gid-2', $user->google_id);
        $this->assertSame('p@b.c', $user->email);
        // No OTP: the number is stored but not marked verified.
        $this->assertNull($user->phone_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('google_pending'));
    }

    public function test_phone_step_404_without_pending_and_skip_follows_mode(): void
    {
        $this->enableGoogle(['google_phone' => 'required']);
        $this->get('/login/google/phone')->assertNotFound();

        $pending = ['google_pending' => ['id' => 'gid-3', 'email' => 's@b.c', 'name' => 'S']];

        // Required mode does not allow skipping.
        $this->withSession($pending)->post('/login/google/phone/skip')->assertNotFound();
        $this->assertGuest();

        // Optional mode shows the skip button and skipping works.
        LoginSettings::save(['google_phone' => 'optional']);
        $this->withSession($pending)->get('/login/google/phone')
            ->assertOk()->assertSee(route('login.google.phone.skip'), false);
        $this->withSession($pending)->post('/login/google/phone/skip')->assertRedirect(route('home'));

        $user = User::where('google_id', 'gid-3')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->phone);
        $this->assertAuthenticatedAs($user);
    }

    public function test_verified_number_cannot_be_taken_over(): void
    {
        $this->enableGoogle();
        $this->makeUser('citizen', '+8801700000023', ['phone_verified_at' => now()]);

        $pending = ['google_pending' => ['id' => 'gid-4', 'email' => 't@b.c', 'name' => 'T']];
        $this->withSession($pending)
            ->post('/login/google/phone', ['phone' => '01700000023'])
            ->assertSessionHasErrors('phone');
        $this->assertGuest();
    }
}
