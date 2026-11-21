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

class AuthorityContactCrudTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $phone): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone' => $phone,
            'display_name' => ucfirst($role),
            'role' => $role,
        ]));
    }

    /** @return array{0:AdminArea,1:AdminArea,2:AdminArea,3:AdminArea} */
    private function areaTree(): array
    {
        $division = AdminArea::create(['level' => 'division', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $district = AdminArea::create(['parent_id' => $division->id, 'level' => 'district', 'name_bn' => 'গাজীপুর', 'name_en' => 'Gazipur']);
        $upazila = AdminArea::create(['parent_id' => $district->id, 'level' => 'upazila', 'name_bn' => 'কালীগঞ্জ', 'name_en' => 'Kaliganj']);
        $union = AdminArea::create(['parent_id' => $upazila->id, 'level' => 'union', 'name_bn' => 'বাহাদুরসাদী', 'name_en' => 'Bahadursadi']);

        return [$division, $district, $upazila, $union];
    }

    private function issue(User $user, AdminArea $area): Issue
    {
        $category = Category::create(['slug' => 'authority-crud', 'name_bn' => 'রাস্তা', 'name_en' => 'Road', 'sort' => 1, 'is_active' => true]);
        $issue = Issue::create([
            'title' => 'Authority contact history',
            'category_id' => $category->id,
            'lat' => 23.8,
            'lng' => 90.4,
            'admin_area_id' => $area->id,
            'progress_status' => 'reported',
            'verification_status' => 'unverified',
            'disposition' => 'active',
        ]);
        $issue->forceFill(['published_at' => now()])->save();

        return $issue;
    }

    public function test_admin_can_select_location_edit_and_soft_delete_a_contact_without_breaking_history(): void
    {
        $admin = $this->user('admin', '01700001001');
        [$division, $district, $upazila, $union] = $this->areaTree();

        $this->actingAs($admin)->post(route('admin.contacts.store'), [
            'admin_area_id' => $union->id,
            'name' => 'District Engineer',
            'position' => 'Engineer',
            'phone' => '01700-001-001',
            'email' => '',
            'whatsapp' => '',
        ])->assertRedirect(route('admin.contacts.index'));

        $contact = AuthorityContact::firstOrFail();
        $this->assertSame($union->id, $contact->admin_area_id);
        $this->assertSame('approved', $contact->status);

        $this->actingAs($admin)->get(route('admin.contacts.edit', $contact))
            ->assertOk()
            ->assertSee('data-contact-form', false)
            ->assertSee('data-area-level="division"', false)
            ->assertSee('data-area-level="district"', false)
            ->assertSee('data-area-level="upazila"', false)
            ->assertSee('data-area-level="union"', false);

        $this->actingAs($admin)->put(route('admin.contacts.update', $contact), [
            'admin_area_id' => $district->id,
            'name' => 'Updated Engineer',
            'position' => 'Executive Engineer',
            'phone' => '01800000000',
            'email' => 'engineer@example.test',
            'whatsapp' => '',
        ])->assertRedirect(route('admin.contacts.index'));

        $contact->refresh();
        $this->assertSame($district->id, $contact->admin_area_id);
        $this->assertSame('Updated Engineer', $contact->name);
        $this->assertSame('approved', $contact->status);

        $issue = $this->issue($admin, $district);
        $history = IssueNotification::create([
            'issue_id' => $issue->id,
            'authority_contact_id' => $contact->id,
            'channel' => 'email',
            'status' => 'sent',
            'sent_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.contacts.destroy', $contact))
            ->assertRedirect(route('admin.contacts.index'));

        $this->assertSoftDeleted('authority_contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('issue_notifications', ['id' => $history->id, 'authority_contact_id' => $contact->id]);
        $this->assertSame($contact->id, IssueNotification::with('contact')->findOrFail($history->id)->contact?->id);
        $this->actingAs($admin)->get(route('admin.contacts.index'))->assertDontSee('Updated Engineer');
    }

    public function test_moderator_cannot_edit_or_delete_someone_elses_contact(): void
    {
        $admin = $this->user('admin', '01700001001');
        $moderator = $this->user('moderator', '01700001002');
        [, $district] = $this->areaTree();
        $contact = AuthorityContact::create([
            'admin_area_id' => $district->id,
            'name' => 'Admin contact',
            'phone' => '01700001003',
            'status' => 'approved',
            'added_by' => $admin->id,
        ]);

        $this->actingAs($moderator)->get(route('admin.contacts.edit', $contact))->assertForbidden();
        $this->actingAs($moderator)->delete(route('admin.contacts.destroy', $contact))->assertForbidden();
        $this->assertDatabaseHas('authority_contacts', ['id' => $contact->id, 'deleted_at' => null]);
    }
}
