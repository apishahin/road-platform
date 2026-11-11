<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+8801712345678';

    protected function setUp(): void
    {
        parent::setUp();
        LoginSettings::save([
            'otp_provider' => 'firebase',
            'firebase_api_key' => 'k',
            'firebase_auth_domain' => 'a.firebaseapp.com',
            'firebase_project_id' => 'p',
        ]);
    }

    private function token(array $over = []): string
    {
        $claims = array_replace_recursive(
            ['auth_time' => time(), 'firebase' => ['sign_in_provider' => 'phone']],
            $over
        );
        $b = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $b('{"alg":"RS256"}') . '.' . $b(json_encode($claims)) . '.' . $b('sig');
    }

    private function fakeLookup(string $phone): void
    {
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response(['users' => [['phoneNumber' => $phone]]], 200)]);
    }

    private function send(string $tok)
    {
        return $this->withSession(['otp_phone' => self::PHONE])
            ->postJson('/login/firebase/verify', ['id_token' => $tok]);
    }

    public function test_valid_token_logs_in(): void
    {
        $this->fakeLookup(self::PHONE);
        $this->send($this->token())->assertOk()->assertJson(['ok' => true]);
        $this->assertAuthenticated();
        $this->assertTrue(User::where('phone', self::PHONE)->whereNotNull('phone_verified_at')->exists());
    }

    public function test_phone_mismatch_is_rejected(): void
    {
        $this->fakeLookup('+8801799999999');
        $this->send($this->token())->assertStatus(422);
        $this->assertGuest();
    }

    public function test_old_token_is_rejected(): void
    {
        $this->fakeLookup(self::PHONE);
        $this->send($this->token(['auth_time' => time() - 3600]))->assertStatus(422);
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_non_phone_token_is_rejected(): void
    {
        $this->fakeLookup(self::PHONE);
        $this->send($this->token(['firebase' => ['sign_in_provider' => 'password']]))->assertStatus(422);
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_rejected_when_provider_is_not_firebase(): void
    {
        LoginSettings::save(['otp_provider' => 'log']);
        $this->fakeLookup(self::PHONE);
        $this->send($this->token())->assertStatus(422);
        $this->assertGuest();
    }

    public function test_new_number_rejected_when_registration_closed(): void
    {
        LoginSettings::save(['registration_open' => false]);
        $this->fakeLookup(self::PHONE);
        $this->send($this->token())->assertStatus(422);
        $this->assertGuest();
        $this->assertFalse(User::where('phone', self::PHONE)->exists());
    }

    public function test_unverified_password_is_removed_on_first_proof(): void
    {
        $u = User::create(['phone' => self::PHONE, 'display_name' => 'T', 'role' => 'citizen']);
        $u->forceFill(['password' => 'secret12345'])->save();
        $this->fakeLookup(self::PHONE);
        $this->send($this->token())->assertOk();
        $this->assertNull($u->fresh()->password);
    }
}