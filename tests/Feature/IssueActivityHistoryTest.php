<?php

namespace Tests\Feature;

use App\Models\{AdminArea, AuditEvent, AuthorityContact, Category, Issue, IssueNotification, Report, StatusEvent, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueActivityHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $phone, string $name, string $role): User
    {
        return User::create([
            'phone' => $phone,
            'display_name' => $name,
            'role' => $role,
        ]);
    }

    private function issue(): Issue
    {
        $category = Category::create([
            'slug' => 'activity-test',
            'name_bn' => 'ইতিহাস পরীক্ষা',
            'name_en' => 'Activity test',
            'sort' => 1,
            'is_active' => true,
        ]);

        $issue = Issue::create([
            'title' => 'Activity history road',
            'category_id' => $category->id,
            'lat' => 23.8103,
            'lng' => 90.4125,
            'progress_status' => 'reported',
            'verification_status' => 'unverified',
            'disposition' => 'active',
            'reports_count' => 1,
            'first_reported_at' => now(),
            'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()])->save();

        return $issue;
    }

    public function test_admin_sees_named_staff_status_moderation_and_authority_activity(): void
    {
        $admin = $this->user('+8801900000181', 'Admin Full Name', 'admin');
        $volunteer = $this->user('+8801900000182', 'Volunteer Full Name', 'volunteer');
        $reporter = $this->user('+8801900000183', 'Reporter', 'citizen');
        $issue = $this->issue();
        $report = Report::create([
            'issue_id' => $issue->id,
            'user_id' => $reporter->id,
            'description' => 'A report that was approved.',
            'lat' => 23.8103,
            'lng' => 90.4125,
            'moderation' => 'approved',
        ]);

        StatusEvent::create([
            'issue_id' => $issue->id,
            'dimension' => 'verification',
            'from_value' => 'unverified',
            'to_value' => 'verified',
            'actor_id' => $volunteer->id,
            'actor_type' => 'volunteer',
            'source_type' => 'site_visit',
            'reason' => 'Volunteer site visit note',
            'created_at' => now()->subMinutes(3),
        ]);
        AuditEvent::create([
            'actor_id' => $admin->id,
            'action' => 'report.approved',
            'target_type' => 'report',
            'target_id' => $report->public_id,
            'details' => ['from' => 'pending', 'to' => 'approved'],
            'created_at' => now()->subMinutes(2),
        ]);

        $area = AdminArea::create(['level' => 'upazila', 'name_bn' => 'Test area', 'name_en' => 'Test area']);
        $contact = AuthorityContact::create([
            'admin_area_id' => $area->id,
            'name' => 'Road Engineer',
            'position' => 'Executive Engineer',
            'status' => 'approved',
            'added_by' => $admin->id,
        ]);
        IssueNotification::create([
            'issue_id' => $issue->id,
            'authority_contact_id' => $contact->id,
            'channel' => 'email',
            'status' => 'sent',
            'sent_by' => $admin->id,
            'message' => 'Please inspect this road.',
        ]);

        $this->actingAs($admin)->get(route('admin.issues.activity', $issue))
            ->assertOk()
            ->assertSee('Volunteer Full Name')
            ->assertSee('Admin Full Name')
            ->assertSee(__('adminusers.role_volunteer'))
            ->assertSee(__('adminusers.role_admin'))
            ->assertSee('Volunteer site visit note')
            ->assertSee('Road Engineer')
            ->assertSee('Please inspect this road.');
    }

    public function test_history_is_administrator_only_and_staff_names_remain_private_on_the_public_page(): void
    {
        $admin = $this->user('+8801900000191', 'Admin Full Name', 'admin');
        $moderator = $this->user('+8801900000192', 'Moderator Full Name', 'moderator');
        $citizen = $this->user('+8801900000193', 'Citizen', 'citizen');
        $issue = $this->issue();

        StatusEvent::create([
            'issue_id' => $issue->id,
            'dimension' => 'verification',
            'from_value' => 'unverified',
            'to_value' => 'reviewed',
            'actor_id' => $moderator->id,
            'actor_type' => 'moderator',
            'source_type' => 'site_visit',
            'reason' => 'Private staff note',
        ]);

        $this->get(route('admin.issues.activity', $issue))->assertRedirect();
        $this->actingAs($citizen)->get(route('admin.issues.activity', $issue))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.issues.activity', $issue))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.issues.activity', $issue))
            ->assertOk()
            ->assertSee('Moderator Full Name')
            ->assertSee('Private staff note');

        // The established public timeline may show a status reason, but it
        // must never identify the volunteer/moderator/admin who recorded it.
        $this->get(route('issues.show', $issue->id))
            ->assertOk()
            ->assertDontSee('Moderator Full Name');
    }
}
