<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountPhoneTest extends TestCase
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

    public function test_guest_is_redirected(): void
    {
        $this->get('/account/phone')->assertRedirect(route('login'));
    }

    public function test_verified_user_sees_status(): void
    {
        $u = $this->user(['phone_verified_at' => now()]);
        $this->actingAs($u)->get('/account/phone')
            ->assertOk()
            ->assertSee(__('acctphone.status_verified'));
    }

    public function test_unverified_user_can_send_and_verify_with_otp(): void
    {
        $u = $this->user(); // phone set, unverified
        $this->assertNull($u->phone_verified_at);

        // Send a code to the saved number.
        $this->actingAs($u)->post('/account/phone/send')
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('otp_codes', ['phone' => '+8801700000030', 'used_at' => null]);

        // Wrong code keeps it unverified.
        $this->actingAs($u)->post('/account/phone/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertNull($u->fresh()->phone_verified_at);

        // Right code verifies.
        $this->plantCode('+8801700000030', '123456');
        $this->actingAs($u)->post('/account/phone/verify', ['code' => '123456'])
            ->assertRedirect(route('account.phone'));
        $this->assertNotNull($u->fresh()->phone_verified_at);
    }

    public function test_user_without_phone_can_add_and_verify(): void
    {
        $u = $this->user(['phone' => null, 'phone_verified_at' => null]);

        $this->actingAs($u)->get('/account/phone')
            ->assertOk()
            ->assertSee('name="phone"', false);

        $this->actingAs($u)->post('/account/phone/send', ['phone' => '01700000055'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('otp_codes', ['phone' => '+8801700000055', 'used_at' => null]);

        $this->plantCode('+8801700000055', '654321');
        $this->actingAs($u)->withSession(['acct_phone' => '+8801700000055'])
            ->post('/account/phone/verify', ['code' => '654321']);

        $u = $u->fresh();
        $this->assertSame('+8801700000055', $u->phone);
        $this->assertNotNull($u->phone_verified_at);
    }

    public function test_number_owned_by_another_account_is_refused(): void
    {
        $other = $this->user(['phone' => '+8801700000066', 'phone_verified_at' => now()]);
        $me = $this->user(['phone' => null]);

        $this->actingAs($me)->post('/account/phone/send', ['phone' => '01700000066'])
            ->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('otp_codes', ['phone' => '+8801700000066']);
        $this->assertNull($me->fresh()->phone);
    }

    public function test_firebase_provider_verifies_via_token(): void
    {
        LoginSettings::save([
            'otp_provider' => 'firebase',
            'firebase_api_key' => 'AIza-test-key',
            'firebase_auth_domain' => 'x.firebaseapp.com',
            'firebase_project_id' => 'project-x',
        ]);
        $u = $this->user();

        // 1st lookup returns the wrong number, 2nd the right one.
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::sequence()
            ->push(['users' => [['phoneNumber' => '+8801700000099']]], 200)
            ->push(['users' => [['phoneNumber' => '+8801700000030']]], 200)]);

        // Token for the wrong number is refused.
        $this->actingAs($u)->withSession(['acct_phone' => '+8801700000030'])
            ->postJson('/account/phone/firebase', ['id_token' => $this->phoneToken()])
            ->assertStatus(422);
        $this->assertNull($u->fresh()->phone_verified_at);

        // Token for the right number verifies.
        $this->actingAs($u)->withSession(['acct_phone' => '+8801700000030'])
            ->postJson('/account/phone/firebase', ['id_token' => $this->phoneToken()])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull($u->fresh()->phone_verified_at);
    }

    public function test_banner_shown_for_unverified_and_hidden_when_verified(): void
    {
        $unverified = $this->user();
        $this->actingAs($unverified)->get('/')
            ->assertOk()
            ->assertSee(__('acctphone.banner_verify'));

        // Dismiss hides it for the session.
        $this->actingAs($unverified)->post('/account/phone/dismiss');
        $this->actingAs($unverified)->get('/')
            ->assertDontSee(__('acctphone.banner_verify'));

        // Each block below is a fresh session (real users have their own).
        $this->flushSession();
        $verified = $this->user(['phone' => '+8801700000031', 'phone_verified_at' => now()]);
        $this->actingAs($verified)->get('/')
            ->assertDontSee(__('acctphone.banner_verify'))
            ->assertDontSee(__('acctphone.banner_add'));

        $this->flushSession();
        $noPhone = $this->user(['phone' => null]);
        $this->actingAs($noPhone)->get('/')
            ->assertSee(__('acctphone.banner_add'));
    }

    private function phoneToken(): string
    {
        $payload = [
            'auth_time' => time(),
            'firebase'  => ['sign_in_provider' => 'phone'],
        ];
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        return "aaa.{$p}.ccc";
    }
}
