<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'phone'     => '+8801700000030',
            'role'      => 'citizen',
            'display_name' => 'Rahim',
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
        $this->get('/account/delete')->assertRedirect(route('login'));
    }

    public function test_staff_cannot_self_delete(): void
    {
        $mod = $this->user(['phone' => '+8801700000091', 'role' => 'moderator']);
        $this->actingAs($mod)->get('/account/delete')
            ->assertOk()
            ->assertSee(__('acctphone.del_staff'));
        $this->actingAs($mod)->post('/account/delete/send')->assertForbidden();
        $this->actingAs($mod)->post('/account/delete', ['code' => '123456'])->assertForbidden();
        $this->assertNotNull($mod->fresh()->phone);
    }

    public function test_no_phone_user_is_pointed_to_verification(): void
    {
        $u = $this->user(['phone' => null, 'phone_verified_at' => null]);
        $this->actingAs($u)->get('/account/delete')
            ->assertOk()
            ->assertSee(__('acctphone.del_nophone'));
        $this->actingAs($u)->post('/account/delete/send')->assertNotFound();
    }

    public function test_wrong_code_keeps_account(): void
    {
        $u = $this->user(['email' => 'r@example.com', 'phone_verified_at' => now()]);
        $this->actingAs($u)->post('/account/delete/send');
        $this->plantCode('+8801700000030', '554433');

        $this->actingAs($u)->post('/account/delete', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $fresh = $u->fresh();
        $this->assertSame('+8801700000030', $fresh->phone);
        $this->assertSame('r@example.com', $fresh->email);
    }

    public function test_correct_code_anonymises_and_logs_out(): void
    {
        $u = $this->user([
            'email' => 'r@example.com',
            'google_id' => 'g-123',
            'password' => bcrypt('secret'),
            'phone_verified_at' => now(),
        ]);
        $this->actingAs($u)->post('/account/delete/send');
        $this->plantCode('+8801700000030', '554433');

        $this->actingAs($u)->post('/account/delete', ['code' => '554433'])
            ->assertRedirect(route('home'));

        $this->assertGuest();

        $fresh = $u->fresh();
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->phone_verified_at);
        $this->assertNull($fresh->display_name);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->google_id);
        $this->assertNull($fresh->password);

        // The row stays (public reports keep a valid author key).
        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'account.delete']);
    }

    public function test_same_number_can_register_fresh_afterwards(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/account/delete/send');
        $this->plantCode('+8801700000030', '554433');
        $this->actingAs($u)->post('/account/delete', ['code' => '554433']);
        $this->assertGuest();

        // The freed number signs up / logs in as a brand-new account.
        $this->post('/login', ['phone' => '+8801700000030']);
        $this->plantCode('+8801700000030', '112299');
        $this->post('/login/verify', ['code' => '112299']);

        $this->assertAuthenticated();
        $this->assertNotSame($u->id, auth()->id());
        $this->assertNull($u->fresh()->phone);
    }
}
