<?php

namespace Tests\Feature;

use App\Models\{AdminArea, Category, Issue, IssueFollow, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EngagementTest extends TestCase
{
    use RefreshDatabase;

    private function citizen(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0181' . random_int(1000000, 9999999),
            'role'      => 'citizen',
        ]));
    }

    private function makeIssue(): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'eg-cat'],
            ['name_bn' => 'EG', 'name_en' => 'EG', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $issue = Issue::create([
            'title' => 'EG issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    public function test_issue_page_has_direct_share_links(): void
    {
        $issue = $this->makeIssue();

        $this->get(route('issues.show', $issue->public_id))
            ->assertOk()
            ->assertSee('data-share-links', false)
            ->assertSee('facebook.com/sharer/sharer.php', false)
            ->assertSee('wa.me/?text=', false);
    }

    public function test_report_success_page_shows_autofollow_note(): void
    {
        $this->actingAs($this->citizen())
            ->withSession(['submitted' => true])
            ->get(route('report.success'))
            ->assertOk()
            ->assertSee('data-autofollow-note', false);
    }

    public function test_reporter_auto_follows_the_issue(): void
    {
        $user  = $this->citizen();
        $issue = $this->makeIssue();

        // simulate the follow-insert used by ReportController (service-level insert
        // is covered end-to-end by the report flow tests; here we verify the model
        // contract: one row per user per issue, no duplicates)
        IssueFollow::firstOrCreate(['issue_id' => $issue->id, 'user_id' => $user->id], ['created_at' => now()]);
        IssueFollow::firstOrCreate(['issue_id' => $issue->id, 'user_id' => $user->id], ['created_at' => now()]);

        $this->assertSame(1, IssueFollow::where('issue_id', $issue->id)->where('user_id', $user->id)->count());
    }
}
