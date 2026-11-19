<?php

namespace Tests\Feature;

use App\Models\{AdminArea, Category, Issue, IssueFollow, User, UserNotification};
use App\Services\IssueStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FollowNotifyTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'user'): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0171' . random_int(1000000, 9999999),
            'role'      => $role,
        ]));
    }

    private function makeIssue(): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'fn-cat'],
            ['name_bn' => 'FN', 'name_en' => 'FN', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $issue = Issue::create([
            'title' => 'FN issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    public function test_guest_cannot_follow_and_user_can_toggle(): void
    {
        $issue = $this->makeIssue();

        $this->post(route('issues.follow', $issue->public_id))->assertRedirect('/login');
        $this->assertSame(0, IssueFollow::count());

        $user = $this->user();
        $this->actingAs($user)->post(route('issues.follow', $issue->public_id))
            ->assertRedirect();
        $this->assertTrue(IssueFollow::where('issue_id', $issue->id)->where('user_id', $user->id)->exists());

        // toggling again unfollows
        $this->actingAs($user)->post(route('issues.follow', $issue->public_id));
        $this->assertFalse(IssueFollow::where('issue_id', $issue->id)->where('user_id', $user->id)->exists());
    }

    public function test_issue_page_shows_follow_state(): void
    {
        $issue = $this->makeIssue();
        $user  = $this->user();

        $this->actingAs($user)->get(route('issues.show', $issue->public_id))
            ->assertOk()->assertSee('data-follow-box', false)->assertSee(__('issues.follow_btn'));

        IssueFollow::create(['issue_id' => $issue->id, 'user_id' => $user->id, 'created_at' => now()]);

        $this->actingAs($user)->get(route('issues.show', $issue->public_id))
            ->assertOk()->assertSee(__('issues.follow_following'));
    }

    public function test_status_change_notifies_followers_not_actor(): void
    {
        $issue    = $this->makeIssue();
        $follower = $this->user();
        $admin    = $this->user('admin');
        IssueFollow::create(['issue_id' => $issue->id, 'user_id' => $follower->id, 'created_at' => now()]);
        IssueFollow::create(['issue_id' => $issue->id, 'user_id' => $admin->id, 'created_at' => now()]);

        app(IssueStatusService::class)->change($issue, 'progress', 'acknowledged', $admin, 'test reason');

        // follower notified, actor (admin) not self-notified
        $this->assertSame(1, UserNotification::where('user_id', $follower->id)->count());
        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->count());

        $n = UserNotification::where('user_id', $follower->id)->first();
        $this->assertNotNull($n->status_event_id);
        $this->assertStringContainsString('/issues/', (string) $n->url);
        $this->assertNull($n->read_at);
    }

    public function test_notifications_page_marks_read_and_is_private(): void
    {
        $this->get(route('notifications.index'))->assertRedirect('/login');

        $user  = $this->user();
        $other = $this->user();
        UserNotification::create([
            'user_id' => $user->id, 'issue_id' => null, 'status_event_id' => null,
            'title' => 'Hello', 'url' => '/issues/x', 'created_at' => now(),
        ]);
        UserNotification::create([
            'user_id' => $other->id, 'issue_id' => null, 'status_event_id' => null,
            'title' => 'Not yours', 'url' => '/issues/y', 'created_at' => now(),
        ]);

        $res = $this->actingAs($user)->get(route('notifications.index'));
        $res->assertOk()->assertSee('data-notifications', false)
            ->assertSee('Hello')->assertDontSee('Not for you');

        // all marked read after viewing
        $this->assertSame(0, UserNotification::where('user_id', $user->id)->whereNull('read_at')->count());
        // other user's untouched
        $this->assertSame(1, UserNotification::where('user_id', $other->id)->whereNull('read_at')->count());
    }
}
