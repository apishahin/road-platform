<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\BlockedIp;
use App\Models\User;
use App\Models\VolunteerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'role'      => 'citizen',
        ], $over)));
    }

    public function test_guest_and_non_admin_are_kept_out(): void
    {
        // House convention: admin pages answer guests with 403 (see login-settings).
        $this->get('/admin')->assertForbidden();
        $this->actingAs($this->user(['phone' => '+8801700000030']))->get('/admin')->assertForbidden();
        $this->actingAs($this->user(['phone' => '+8801700000090', 'role' => 'moderator']))->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_counts_that_match_the_database(): void
    {
        $admin = $this->user(['phone' => '+8801700000099', 'role' => 'admin', 'phone_verified_at' => now()]);

        // Two unverified + one numberless citizen, one blocked IP, one pending application.
        $this->user(['phone' => '+8801700000031']);
        $this->user(['phone' => '+8801700000032']);
        $this->user(['phone' => null, 'phone_verified_at' => null]);
        BlockedIp::create(['ip' => '203.0.113.9']);
        $area = AdminArea::unguarded(fn () => AdminArea::create(['level' => 'division', 'name_bn' => 'ঢাকা']));
        VolunteerApplication::unguarded(fn () => VolunteerApplication::create([
            'user_id' => $admin->id, 'admin_area_id' => $area->id,
            'message' => 'test', 'agreed_at' => now(),
        ]));

        $r = $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee(__('admindash.card_reports_pending'))
            ->assertSee(__('admindash.card_users_unverified'))
            ->assertSee(__('admindash.card_users_nophone'))
            ->assertSee(__('admindash.card_blocked_ips'))
            ->assertSee(__('admindash.card_issues_week'));

        // 2 unverified, 1 numberless, 1 blocked IP, 1 pending application.
        // Numbers render inside their own cards: count first, label after.
        $html = $r->getContent();
        $this->assertMatchesRegularExpression('/>\s*2\s*<\/p>.*?' . preg_quote(__('admindash.card_users_unverified'), '/') . '/s', $html);
        $this->assertMatchesRegularExpression('/>\s*1\s*<\/p>.*?' . preg_quote(__('admindash.card_blocked_ips'), '/') . '/s', $html);
    }

    public function test_dashboard_link_appears_in_staff_menu(): void
    {
        $admin = $this->user(['phone' => '+8801700000099', 'role' => 'admin', 'phone_verified_at' => now()]);
        $this->actingAs($admin)->get('/')->assertOk()->assertSee(route('admin.dashboard'));
    }
}
