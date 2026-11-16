<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserBlockTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'role'      => 'citizen',
        ], $over)));
    }

    private function admin(string $phone = '+8801700000099'): User
    {
        return $this->user(['phone' => $phone, 'role' => 'admin', 'phone_verified_at' => now()]);
    }

    private function sessionFor(int $userId, string $ip): void
    {
        DB::table('sessions')->insert([
            'id' => (string) Str::random(40),
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);
    }

    public function test_block_bans_kills_sessions_and_blocks_their_ips(): void
    {
        $admin = $this->admin();
        $u = $this->user(['phone' => '+8801700000030']);
        $this->sessionFor($u->id, '203.0.113.10');
        $this->sessionFor($u->id, '203.0.113.11');

        $this->actingAs($admin)->post("/admin/users/{$u->id}/block")->assertRedirect();

        $this->assertTrue((bool) $u->fresh()->is_banned);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $u->id)->count());
        $this->assertDatabaseHas('blocked_ips', ['ip' => '203.0.113.10', 'user_id' => $u->id]);
        $this->assertDatabaseHas('blocked_ips', ['ip' => '203.0.113.11', 'user_id' => $u->id]);

        // Anything from a blocked IP gets a 403, logged in or not.
        $this->call('GET', '/', [], [], [], ['REMOTE_ADDR' => '203.0.113.10'])->assertForbidden();
    }

    public function test_unblock_lifts_ban_and_ip_blocks(): void
    {
        $admin = $this->admin();
        $u = $this->user(['phone' => '+8801700000030']);
        $this->sessionFor($u->id, '203.0.113.10');
        $this->actingAs($admin)->post("/admin/users/{$u->id}/block");

        $this->actingAs($admin)->post("/admin/users/{$u->id}/unblock")->assertRedirect();

        $this->assertFalse((bool) $u->fresh()->is_banned);
        $this->assertSame(0, BlockedIp::where('user_id', $u->id)->count());
        $this->call('GET', '/', [], [], [], ['REMOTE_ADDR' => '203.0.113.10'])->assertOk();
    }

    public function test_admin_delete_anonymises_and_can_be_undone_nowhere(): void
    {
        $admin = $this->admin();
        $u = $this->user([
            'phone' => '+8801700000030', 'email' => 'a@b.c', 'google_id' => 'g1',
            'password' => bcrypt('x'), 'display_name' => 'Rahim',
        ]);
        $this->sessionFor($u->id, '203.0.113.10');
        $this->actingAs($admin)->post("/admin/users/{$u->id}/block");

        $this->actingAs($admin)->post("/admin/users/{$u->id}/delete")->assertRedirect();

        $fresh = $u->fresh();                       // row kept (public reports stay attributed)
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->google_id);
        $this->assertNull($fresh->display_name);
        $this->assertNull($fresh->password);
        $this->assertSame(0, BlockedIp::where('user_id', $u->id)->count()); // ban lifted: nobody owns that ip block anymore
        $this->call('GET', '/', [], [], [], ['REMOTE_ADDR' => '203.0.113.10'])->assertOk();
    }

    public function test_self_and_admins_are_protected_and_citizens_cannot_act(): void
    {
        $admin = $this->admin();
        $other = $this->admin('+8801700000098');

        $this->actingAs($admin)->post("/admin/users/{$admin->id}/block")->assertRedirect();
        $this->assertFalse((bool) $admin->fresh()->is_banned);

        $this->actingAs($admin)->post("/admin/users/{$other->id}/block")->assertRedirect();
        $this->assertFalse((bool) $other->fresh()->is_banned);

        $citizen = $this->user(['phone' => '+8801700000030']);
        $this->actingAs($citizen)->post("/admin/users/{$citizen->id}/block")->assertForbidden();
        $this->actingAs($citizen)->post("/admin/users/{$citizen->id}/delete")->assertForbidden();
    }

    public function test_single_ip_can_be_lifted_by_hand(): void
    {
        $admin = $this->admin();
        $u = $this->user(['phone' => '+8801700000030']);
        $this->sessionFor($u->id, '203.0.113.10');
        $this->actingAs($admin)->post("/admin/users/{$u->id}/block");

        $row = BlockedIp::where('ip', '203.0.113.10')->firstOrFail();
        $this->actingAs($admin)->post("/admin/blocked-ips/{$row->id}/delete")->assertRedirect();
        $this->assertDatabaseMissing('blocked_ips', ['ip' => '203.0.113.10']);

        // The user row itself stays banned until unblocked.
        $this->assertTrue((bool) $u->fresh()->is_banned);
    }

    public function test_blocked_page_shows_message_in_both_locales(): void
    {
        BlockedIp::create(['ip' => '198.51.100.7']);
        $this->call('GET', '/', [], [], [], ['REMOTE_ADDR' => '198.51.100.7'])
            ->assertForbidden()
            ->assertSee(__('adminusers.ip_blocked_msg'), false);
    }
}
