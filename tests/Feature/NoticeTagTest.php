<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\AuthorityContact;
use App\Models\Category;
use App\Models\Issue;
use App\Models\IssueNotification;
use App\Models\User;
use App\Services\HomeData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoticeTagTest extends TestCase
{
    use RefreshDatabase;

    private function makeData(): array
    {
        $user = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '01700000001',
            'role'      => 'moderator',
        ]));

        $area = AdminArea::unguarded(fn () => AdminArea::create([
            'level'   => 'upazila',
            'name_bn' => 'Test Upazila',
        ]));

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

        $contact = AuthorityContact::unguarded(fn () => AuthorityContact::create([
            'admin_area_id' => $area->id,
            'name'          => 'SecretPersonName',
            'position'      => 'Executive Engineer',
            'phone'         => '017112233444',
            'email'         => 'secret.person@example.com',
            'whatsapp'      => '01755667788',
            'status'        => 'approved',
            'added_by'      => $user->id,
        ]));

        return [$issue, $contact, $user];
    }

    private function notify(Issue $issue, AuthorityContact $contact, User $user, string $status): void
    {
        IssueNotification::unguarded(fn () => IssueNotification::create([
            'issue_id'             => $issue->id,
            'authority_contact_id' => $contact->id,
            'channel'              => 'email',
            'status'               => $status,
            'sent_by'              => $user->id,
        ]));
    }

    public function test_sent_notification_shows_tag_on_list(): void
    {
        [$issue, $contact, $user] = $this->makeData();
        $this->notify($issue, $contact, $user, 'sent');

        $this->get('/issues')->assertOk()->assertSee(__('issues.notified_tag'));
    }

    public function test_logged_failed_and_opened_show_no_tag_on_list(): void
    {
        [$issue, $contact, $user] = $this->makeData();
        foreach (['logged', 'failed', 'opened'] as $status) {
            $this->notify($issue, $contact, $user, $status);
        }

        $this->get('/issues')->assertOk()->assertDontSee(__('issues.notified_tag'));
    }

    public function test_home_data_flags_only_sent(): void
    {
        [$issue, $contact, $user] = $this->makeData();
        $this->notify($issue, $contact, $user, 'logged');

        $data = app(HomeData::class)->build();
        $this->assertFalse($data['mapIssues'][0]['notified']);
        $this->assertSame(0, $data['stats']['notified']);

        $this->notify($issue, $contact, $user, 'sent');

        $data = app(HomeData::class)->build();
        $this->assertTrue($data['mapIssues'][0]['notified']);
        $this->assertSame(1, $data['stats']['notified']);
    }

    public function test_mark_and_presenter_flag_only_sent(): void
    {
        [$issue, $contact, $user] = $this->makeData();
        $this->notify($issue, $contact, $user, 'logged');

        \App\Support\IssueNotice::mark([$issue]);
        $this->assertFalse(\App\Support\IssuePresenter::map($issue)['notified']);

        $this->notify($issue, $contact, $user, 'sent');

        \App\Support\IssueNotice::mark([$issue]);
        $this->assertTrue(\App\Support\IssuePresenter::map($issue)['notified']);
    }
}