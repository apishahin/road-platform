<?php

namespace Tests\Feature;

use App\Models\{AdminArea, Category, Issue, Report, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModerationEaseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0171' . random_int(1000000, 9999999),
            'role'      => 'admin',
        ]));
    }

    private function pendingReport(): void
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'me-cat'],
            ['name_bn' => 'ME', 'name_en' => 'ME', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $issue = Issue::create([
            'title' => 'ME issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();
        $user = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0181' . random_int(1000000, 9999999),
            'role'      => 'citizen',
        ]));
        Report::unguarded(fn () => Report::create([
            'public_id' => (string) Str::ulid(), 'issue_id' => $issue->id, 'user_id' => $user->id,
            'description' => 'test', 'lat' => 23.8, 'lng' => 90.4, 'moderation' => 'pending',
        ]));
    }

    public function test_nav_shows_pending_badge_for_moderators(): void
    {
        $this->pendingReport();

        // any page with the staff nav shows the pending count
        $res = $this->actingAs($this->admin())->get(route('admin.moderation.index'));
        $res->assertOk()->assertSee('>' . 1 . '</span>', false);
    }

    public function test_moderation_page_has_keyboard_shortcuts(): void
    {
        $this->pendingReport();

        $this->actingAs($this->admin())->get(route('admin.moderation.index'))
            ->assertOk()
            ->assertSee('data-mod-kb', false)
            ->assertSee('data-mod-approve', false)
            ->assertSee('data-mod-hide', false)
            ->assertSee('data-mod-card', false);
    }
}
