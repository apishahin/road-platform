<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\AuthorityContact;
use App\Models\Category;
use App\Models\Issue;
use App\Models\IssueNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IssueNotifiedBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \App\Support\AppearanceSettings::reset();
        parent::tearDown();
    }

    private function makeData(): array
    {
        // These tests check the detailed page (the old layout)
        \App\Support\AppearanceSettings::save(['issue_view' => 'detailed']);

        return $this->makeRows();
    }

    private function makeRows(): array
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
            'phone'         => '01711223344',
            'email'         => 'secret.person@example.com',
            'whatsapp'      => '01755667788',
            'status'        => 'approved',
            'added_by'      => $user->id,
        ]));

        return [$issue, $contact, $user];
    }

    private function addNotification(Issue $issue, AuthorityContact $contact, User $user, string $status, string $channel = 'email'): void
    {
        IssueNotification::unguarded(fn () => IssueNotification::create([
            'issue_id'             => $issue->id,
            'authority_contact_id' => $contact->id,
            'channel'              => $channel,
            'status'               => $status,
            'sent_by'              => $user->id,
        ]));
    }

    public function test_sent_email_shows_block_with_position(): void
    {
        [$issue, $contact, $user] = $this->makeData();
        $this->addNotification($issue, $contact, $user, 'sent');

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertSee(__('issuenotified.title'))
            ->assertSee('Executive Engineer');
    }

    public function test_logged_failed_and_opened_are_not_shown(): void
    {
        [$issue, $contact, $user] = $this->makeData();
        $this->addNotification($issue, $contact, $user, 'logged');
        $this->addNotification($issue, $contact, $user, 'failed');
        $this->addNotification($issue, $contact, $user, 'opened', 'whatsapp');

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertDontSee(__('issuenotified.title'))
            ->assertDontSee('Executive Engineer');
    }

    public function test_contact_name_phone_email_are_never_public(): void
    {
        [$issue, $contact, $user] = $this->makeData();
        $this->addNotification($issue, $contact, $user, 'sent');

        $this->get('/issue/' . $issue->id)
            ->assertOk()
            ->assertDontSee('SecretPersonName')
            ->assertDontSee('01711223344')
            ->assertDontSee('01755667788')
            ->assertDontSee('secret.person@example.com');
    }
}