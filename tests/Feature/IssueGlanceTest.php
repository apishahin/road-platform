<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\AuthorityContact;
use App\Models\Category;
use App\Models\Issue;
use App\Models\IssueNotification;
use App\Models\Observation;
use App\Models\User;
use App\Support\AppearanceSettings;
use App\Support\IssuePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IssueGlanceTest extends TestCase
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
            'slug'      => 'test-cat',
            'name_bn'   => 'Test Cat',
            'name_en'   => 'Test Cat',
            'sort'      => 1,
            'is_active' => 1,
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

    private function makeContact(User $by): AuthorityContact
    {
        $area = AdminArea::unguarded(fn () => AdminArea::create([
            'level'   => 'upazila',
            'name_bn' => 'Test Upazila',
        ]));

        return AuthorityContact::unguarded(fn () => AuthorityContact::create([
            'admin_area_id' => $area->id,
            'name'          => 'SecretPersonName',
            'position'      => 'Executive Engineer',
            'phone'         => '01711122344',
            'email'         => 'secret.person@example.com',
            'whatsapp'      => '01755667788',
            'status'        => 'approved',
            'added_by'      => $by->id,
        ]));
    }

    private function notify(Issue $issue, AuthorityContact $contact, User $by, string $status): void
    {
        IssueNotification::unguarded(fn () => IssueNotification::create([
            'issue_id'             => $issue->id,
            'authority_contact_id' => $contact->id,
            'channel'              => 'email',
            'status'               => $status,
            'sent_by'              => $by->id,
        ]));
    }

    public function test_default_page_is_simple(): void
    {
        $issue = $this->makeIssue();

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertSee(__('issues.glance_title'));
    }

    public function test_simple_view_shows_position_for_sent_only(): void
    {
        $staff   = $this->makeUser('01700000001', 'moderator');
        $issue   = $this->makeIssue();
        $contact = $this->makeContact($staff);
        $this->notify($issue, $contact, $staff, 'sent');

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertSee('Executive Engineer')
            ->assertDontSee(__('issuenotified.title'));
    }

    public function test_simple_view_hides_logged_failed_opened(): void
    {
        $staff   = $this->makeUser('01700000001', 'moderator');
        $issue   = $this->makeIssue();
        $contact = $this->makeContact($staff);
        $this->notify($issue, $contact, $staff, 'logged');
        $this->notify($issue, $contact, $staff, 'failed');
        $this->notify($issue, $contact, $staff, 'opened');

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertDontSee('Executive Engineer')
            ->assertSee(__('issues.glance_not_notified'));
    }

    public function test_contact_details_are_never_public_in_simple_view(): void
    {
        $staff   = $this->makeUser('01700000001', 'moderator');
        $issue   = $this->makeIssue();
        $contact = $this->makeContact($staff);
        $this->notify($issue, $contact, $staff, 'sent');

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertDontSee('SecretPersonName')
            ->assertDontSee('01711122344')
            ->assertDontSee('secret.person@example.com')
            ->assertDontSee('01755667788');
    }

    public function test_detailed_view_has_no_glance_box(): void
    {
        AppearanceSettings::save(['issue_view' => 'detailed']);
        $issue = $this->makeIssue();

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertDontSee(__('issues.glance_title'));
    }

    public function test_guest_cannot_send_observation(): void
    {
        $issue = $this->makeIssue();

        $this->post('/issues/' . $issue->public_id . '/observe', ['type' => 'fixed'])
            ->assertStatus(302);

        $this->assertSame(0, Observation::count());
    }

    public function test_citizen_claim_is_counted_but_never_repairs(): void
    {
        $citizen = $this->makeUser('01700000002', 'citizen');
        $issue   = $this->makeIssue();

        $this->actingAs($citizen)
            ->post('/issues/' . $issue->public_id . '/observe', ['type' => 'fixed', 'note' => 'SECRETNOTE'])
            ->assertStatus(302);

        $this->assertSame(1, Observation::count());
        $this->assertSame('fixed', Observation::first()->type);
        $this->assertSame('reported', $issue->fresh()->progress_status);

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertSee(__('issues.glance_fixed_claims', ['count' => IssuePresenter::num(1)]))
            ->assertSee(__('issues.glance_claim_note'))
            ->assertDontSee('SECRETNOTE');
    }

    public function test_new_answer_replaces_the_old_one(): void
    {
        $citizen = $this->makeUser('01700000002', 'citizen');
        $issue   = $this->makeIssue();
        $url     = '/issues/' . $issue->public_id . '/observe';

        $this->actingAs($citizen)->post($url, ['type' => 'fixed']);
        $this->actingAs($citizen)->post($url, ['type' => 'still_exists']);

        $this->assertSame(1, Observation::count());
        $this->assertSame('still_exists', Observation::first()->type);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $citizen = $this->makeUser('01700000002', 'citizen');
        $issue   = $this->makeIssue();

        $this->actingAs($citizen)
            ->post('/issues/' . $issue->public_id . '/observe', ['type' => 'repaired'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Observation::count());
    }

    public function test_duplicate_issue_does_not_accept_observation(): void
    {
        $citizen = $this->makeUser('01700000002', 'citizen');
        $issue   = $this->makeIssue();
        $issue->forceFill(['disposition' => 'duplicate'])->save();

        $this->actingAs($citizen)
            ->post('/issues/' . $issue->public_id . '/observe', ['type' => 'fixed'])
            ->assertNotFound();

        $this->assertSame(0, Observation::count());
    }
}