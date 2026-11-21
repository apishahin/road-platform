<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use App\Models\ModeratorArea;
use App\Models\Observation;
use App\Models\StatusEvent;
use App\Models\User;
use App\Support\AppearanceSettings;
use App\Support\IssuePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IssueVerifyTest extends TestCase
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

    private function makeArea(string $name): AdminArea
    {
        return AdminArea::unguarded(fn () => AdminArea::create([
            'level'   => 'upazila',
            'name_bn' => $name,
        ]));
    }

    private function volunteerIn(AdminArea $area, string $phone): User
    {
        $u = $this->makeUser($phone, 'volunteer');
        ModeratorArea::unguarded(fn () => ModeratorArea::create([
            'user_id'       => $u->id,
            'admin_area_id' => $area->id,
        ]));

        return $u;
    }

    private function makeIssue(string $title = 'Test issue', ?AdminArea $area = null): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'test-cat'],
            ['name_bn' => 'Test Cat', 'name_en' => 'Test Cat', 'sort' => 1, 'is_active' => 1]
        ));

        $issue = Issue::create([
            'title'               => $title,
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
        $fill = ['published_at' => now()->toDateTimeString()];
        if ($area) {
            $fill['admin_area_id'] = $area->id;
        }
        $issue->forceFill($fill)->save();

        return $issue;
    }

    private function body(array $over = []): array
    {
        return array_merge(['type' => 'repaired', 'note' => 'I saw the road repaired', 'seen' => '1'], $over);
    }

    private function url(Issue $issue): string
    {
        return '/issues/' . $issue->public_id . '/verify';
    }

    public function test_guest_is_redirected(): void
    {
        $issue = $this->makeIssue();

        $this->post($this->url($issue), $this->body())->assertStatus(302);
        $this->assertSame('reported', $issue->fresh()->progress_status);
    }

    public function test_citizen_cannot_verify(): void
    {
        $citizen = $this->makeUser('01700000002', 'citizen');
        $issue   = $this->makeIssue();

        $this->actingAs($citizen)->post($this->url($issue), $this->body())->assertForbidden();
        $this->assertSame('reported', $issue->fresh()->progress_status);
    }

    public function test_volunteer_in_area_can_confirm_repair(): void
    {
        $area  = $this->makeArea('Area A');
        $vol   = $this->volunteerIn($area, '01700000021');
        $issue = $this->makeIssue('Test issue', $area);

        $this->actingAs($vol)->post($this->url($issue), $this->body())->assertRedirect();

        $f = $issue->fresh();
        $this->assertSame('repaired', $f->progress_status);
        $this->assertSame('verified', $f->verification_status);
        $this->assertSame(2, StatusEvent::where('issue_id', $issue->id)->where('source_type', 'site_visit')->count());
        $this->assertSame('volunteer', StatusEvent::where('issue_id', $issue->id)->orderBy('id')->first()->actor_type);

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertSee(__('issues.glance_role_volunteer', ['count' => IssuePresenter::num(1)]))
            ->assertDontSee('01700000021');
    }

    public function test_volunteer_outside_area_or_without_area_is_forbidden(): void
    {
        $a = $this->makeArea('Area A');
        $b = $this->makeArea('Area B');
        $vol = $this->volunteerIn($a, '01700000021');

        $inB    = $this->makeIssue('Issue in B', $b);
        $noArea = $this->makeIssue('Issue without area');

        $this->actingAs($vol)->post($this->url($inB), $this->body())->assertForbidden();
        $this->actingAs($vol)->post($this->url($noArea), $this->body())->assertForbidden();

        $this->assertSame('reported', $inB->fresh()->progress_status);
        $this->assertSame('reported', $noArea->fresh()->progress_status);
    }

    public function test_still_exists_does_not_repair(): void
    {
        $mod   = $this->makeUser('01700000031', 'moderator');
        $issue = $this->makeIssue();

        $this->actingAs($mod)->post($this->url($issue), $this->body(['type' => 'still_exists', 'note' => 'Still broken']))->assertRedirect();

        $f = $issue->fresh();
        $this->assertSame('reported', $f->progress_status);
        $this->assertSame('reviewed', $f->verification_status);
    }

    public function test_moderator_can_confirm_repair_without_area(): void
    {
        $mod   = $this->makeUser('01700000031', 'moderator');
        $issue = $this->makeIssue();

        $this->actingAs($mod)->post($this->url($issue), $this->body())->assertRedirect();

        $this->assertSame('repaired', $issue->fresh()->progress_status);
    }

    public function test_still_exists_on_repaired_issue_reopens_it(): void
    {
        $mod   = $this->makeUser('01700000031', 'moderator');
        $issue = $this->makeIssue();
        $issue->forceFill(['progress_status' => 'repaired', 'verification_status' => 'verified'])->save();

        $this->actingAs($mod)->post($this->url($issue), $this->body(['type' => 'still_exists', 'note' => 'Hole is back']))->assertRedirect();

        $f = $issue->fresh();
        $this->assertSame('reopened', $f->disposition);
        $this->assertSame('repaired', $f->progress_status);
        $this->assertSame('verified', $f->verification_status);
    }

    public function test_note_is_optional_and_a_neutral_audit_reason_is_recorded(): void
    {
        $mod   = $this->makeUser('01700000031', 'moderator');
        $issue = $this->makeIssue();

        $this->actingAs($mod)->post($this->url($issue), $this->body(['note' => '']))->assertRedirect();

        $this->assertSame('repaired', $issue->fresh()->progress_status);
        $this->assertDatabaseHas('status_events', [
            'issue_id' => $issue->id,
            'reason' => __('issues.verify_no_note'),
        ]);
    }

    public function test_seen_checkbox_is_required(): void
    {
        $mod   = $this->makeUser('01700000031', 'moderator');
        $issue = $this->makeIssue();

        $this->actingAs($mod)->post($this->url($issue), ['type' => 'repaired', 'note' => 'ok ok'])->assertSessionHasErrors('seen');
        $this->assertSame('reported', $issue->fresh()->progress_status);
    }

    public function test_duplicate_issue_is_not_found(): void
    {
        $mod   = $this->makeUser('01700000031', 'moderator');
        $issue = $this->makeIssue();
        $issue->forceFill(['disposition' => 'duplicate'])->save();

        $this->actingAs($mod)->post($this->url($issue), $this->body())->assertNotFound();
    }

    public function test_form_is_shown_to_moderator_only(): void
    {
        $mod     = $this->makeUser('01700000031', 'moderator');
        $citizen = $this->makeUser('01700000002', 'citizen');
        $issue   = $this->makeIssue();

        $this->actingAs($mod)->get('/issue/' . $issue->id)->assertOk()->assertSee(__('issues.verify_title'));
        $this->actingAs($citizen)->get('/issue/' . $issue->id)->assertOk()->assertDontSee(__('issues.verify_title'));
    }

    public function test_queue_lists_claims_for_moderator(): void
    {
        $mod     = $this->makeUser('01700000031', 'moderator');
        $citizen = $this->makeUser('01700000002', 'citizen');
        $claimed = $this->makeIssue('Claimed road');
        $quiet   = $this->makeIssue('Quiet road');
        Observation::create(['issue_id' => $claimed->id, 'user_id' => $citizen->id, 'type' => 'fixed']);

        $this->actingAs($mod)->get('/verify-queue')
            ->assertOk()
            ->assertSee('Claimed road')
            ->assertDontSee('Quiet road');
    }

    public function test_queue_is_closed_to_citizens(): void
    {
        $citizen = $this->makeUser('01700000002', 'citizen');

        $this->actingAs($citizen)->get('/verify-queue')->assertForbidden();
    }

    public function test_queue_respects_volunteer_area(): void
    {
        $a = $this->makeArea('Area A');
        $b = $this->makeArea('Area B');
        $vol     = $this->volunteerIn($a, '01700000021');
        $citizen = $this->makeUser('01700000002', 'citizen');

        $inA = $this->makeIssue('Road in A', $a);
        $inB = $this->makeIssue('Road in B', $b);
        Observation::create(['issue_id' => $inA->id, 'user_id' => $citizen->id, 'type' => 'fixed']);
        Observation::create(['issue_id' => $inB->id, 'user_id' => $citizen->id, 'type' => 'fixed']);

        $this->actingAs($vol)->get('/verify-queue')
            ->assertOk()
            ->assertSee('Road in A')
            ->assertDontSee('Road in B');
    }
}