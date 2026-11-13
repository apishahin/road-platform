<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Issue;
use App\Models\Report;
use App\Models\StatusEvent;
use App\Models\User;
use App\Support\AppearanceSettings;
use App\Support\IssuePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IssueGlanceWhoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppearanceSettings::reset();
    }

    protected function tearDown(): void
    {
        AppearanceSettings::reset();
        parent::tearDown();
    }

    private function makeUser(string $phone, string $role): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => $phone,
            'role'      => $role,
        ]));
    }

    private function makeIssue(): Issue
    {
        $cat = Category::unguarded(fn () => Category::create([
            'slug' => 'test-cat', 'name_bn' => 'Test Cat', 'name_en' => 'Test Cat', 'sort' => 1, 'is_active' => 1,
        ]));

        $issue = Issue::create([
            'title'               => 'Test issue',
            'category_id'         => $cat->id,
            'lat'                 => 24.5700000,
            'lng'                 => 90.4000000,
            'progress_status'     => 'reported',
            'verification_status' => 'unverified',
            'disposition'         => 'active',
            'reports_count'       => 1,
            'first_reported_at'   => now(),
            'last_activity_at'    => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    private function report(Issue $issue, User $user, string $moderation): void
    {
        Report::create([
            'issue_id'    => $issue->id,
            'user_id'     => $user->id,
            'description' => 'desc',
            'lat'         => 24.57,
            'lng'         => 90.40,
            'moderation'  => $moderation,
        ]);
    }

    private function visit(Issue $issue, User $actor, string $source): void
    {
        StatusEvent::create([
            'issue_id'    => $issue->id,
            'dimension'   => 'verification',
            'from_value'  => 'unverified',
            'to_value'    => 'reviewed',
            'actor_id'    => $actor->id,
            'actor_type'  => $actor->role,
            'source_type' => $source,
            'reason'      => 'visited',
        ]);
    }

    private function role(string $role, int $n): string
    {
        return __('issues.glance_role_' . $role, ['count' => IssuePresenter::num($n)]);
    }

    public function test_reporters_are_counted_by_role_only(): void
    {
        $issue = $this->makeIssue();
        $c1 = $this->makeUser('01700000011', 'citizen');
        $c2 = $this->makeUser('01700000012', 'citizen');
        $v  = $this->makeUser('01700000013', 'volunteer');
        $p  = $this->makeUser('01700000014', 'citizen');

        $this->report($issue, $c1, 'approved');
        $this->report($issue, $c1, 'approved');
        $this->report($issue, $c2, 'approved');
        $this->report($issue, $v, 'approved');
        $this->report($issue, $p, 'pending');

        $this->get('/issues/' . $issue->public_id)
            ->assertOk()
            ->assertSee($this->role('citizen', 2))
            ->assertSee($this->role('volunteer', 1))
            ->assertDontSee($this->role('citizen', 3))
            ->assertDontSee('01700000011')
            ->assertDontSee('01700000013');
    }

    public function test_site_visit_by_volunteer_is_shown_as_role(): void
    {
        $issue = $this->makeIssue();
        $v = $this->makeUser('01700000013', 'volunteer');
        $this->visit($issue, $v, 'site_visit');

        $this->get('/issues/' . $issue->public_id)
            ->assertOk()
            ->assertSee($this->role('volunteer', 1))
            ->assertDontSee('01700000013');
    }

    public function test_no_site_visit_line_when_nobody_visited(): void
    {
        $issue = $this->makeIssue();

        $this->get('/issues/' . $issue->public_id)
            ->assertOk()
            ->assertDontSee($this->role('volunteer', 1))
            ->assertDontSee($this->role('moderator', 1));
    }

    public function test_non_site_visit_source_is_not_counted(): void
    {
        $issue = $this->makeIssue();
        $v = $this->makeUser('01700000013', 'volunteer');
        $this->visit($issue, $v, 'platform');

        $this->get('/issues/' . $issue->public_id)
            ->assertOk()
            ->assertDontSee($this->role('volunteer', 1));
    }
}