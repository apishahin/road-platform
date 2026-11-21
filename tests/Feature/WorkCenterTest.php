<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Evidence;
use App\Models\Issue;
use App\Models\ModeratorArea;
use App\Models\Observation;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkCenterTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $phone): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'display_name' => ucfirst($role),
            'phone' => $phone,
            'role' => $role,
        ]));
    }

    /** @return array{0:AdminArea,1:AdminArea,2:AdminArea,3:AdminArea} */
    private function areaTree(string $suffix = ''): array
    {
        $division = AdminArea::create(['level' => 'division', 'name_bn' => 'ঢাকা'.$suffix, 'name_en' => 'Dhaka'.$suffix]);
        $district = AdminArea::create(['parent_id' => $division->id, 'level' => 'district', 'name_bn' => 'গাজীপুর'.$suffix, 'name_en' => 'Gazipur'.$suffix]);
        $upazila = AdminArea::create(['parent_id' => $district->id, 'level' => 'upazila', 'name_bn' => 'কালীগঞ্জ'.$suffix, 'name_en' => 'Kaliganj'.$suffix]);
        $union = AdminArea::create(['parent_id' => $upazila->id, 'level' => 'union', 'name_bn' => 'বাহাদুরসাদী'.$suffix, 'name_en' => 'Bahadursadi'.$suffix]);

        return [$division, $district, $upazila, $union];
    }

    private function issue(AdminArea $area, string $title = 'Bridge needs review'): Issue
    {
        $category = Category::firstOrCreate(
            ['slug' => 'work-center-road'],
            ['name_bn' => 'রাস্তা', 'name_en' => 'Road', 'sort' => 1, 'is_active' => true]
        );
        $issue = Issue::create([
            'title' => $title,
            'category_id' => $category->id,
            'lat' => 23.8,
            'lng' => 90.4,
            'admin_area_id' => $area->id,
            'progress_status' => 'reported',
            'verification_status' => 'unverified',
            'disposition' => 'active',
            'reports_count' => 1,
            'first_reported_at' => now()->subDays(3),
            'last_activity_at' => now()->subHour(),
        ]);
        $issue->forceFill(['published_at' => now()->subDays(2)])->save();

        return $issue;
    }

    private function queueData(Issue $issue, AdminArea $area, User $reporter): void
    {
        $report = Report::create([
            'issue_id' => $issue->id,
            'user_id' => $reporter->id,
            'suggested_area_id' => $area->id,
            'description' => 'Water has damaged the bridge approach.',
            'lat' => 23.8,
            'lng' => 90.4,
            'moderation' => 'pending',
        ]);
        $observation = Observation::create([
            'issue_id' => $issue->id,
            'user_id' => $reporter->id,
            'type' => 'fixed',
        ]);
        Evidence::unguarded(fn () => Evidence::create([
            'report_id' => null,
            'status_event_id' => null,
            'observation_id' => $observation->id,
            'uploader_id' => $reporter->id,
            'public_path' => 'work-center-test.jpg',
            'moderation' => 'pending',
        ]));

        $this->assertNotNull($report->id);
    }

    public function test_admin_sees_one_unified_location_filterable_work_center_and_json_updates(): void
    {
        $admin = $this->user('admin', '01700002001');
        $reporter = $this->user('citizen', '01700002002');
        [$division, $district, $upazila, $union] = $this->areaTree();
        $issue = $this->issue($union, 'Gazipur bridge repair');
        $this->queueData($issue, $union, $reporter);

        $this->actingAs($admin)->get(route('admin.work-center'))
            ->assertOk()
            ->assertSee(__('workcenter.title'))
            ->assertSee(__('workcenter.tab_moderation'))
            ->assertSee(__('workcenter.tab_verify'))
            ->assertSee(__('workcenter.tab_photos'))
            ->assertSee(__('workcenter.tab_issues'))
            ->assertSee('Gazipur bridge repair')
            ->assertSee('data-work-area="division"', false)
            ->assertSee('data-work-area="district"', false)
            ->assertSee('data-work-area="upazila"', false)
            ->assertSee('data-work-area="union"', false);

        // Lifecycle timestamps are hydrated as dates, so the Issue Management
        // tab can safely format the publication time for existing DB records.
        $this->actingAs($admin)->get(route('admin.work-center', ['tab' => 'issues']))
            ->assertOk()
            ->assertSee('Gazipur bridge repair');

        $this->actingAs($admin)->withHeaders(['Accept' => 'application/json', 'X-Work-Center' => '1'])
            ->get(route('admin.work-center', [
                'tab' => 'all',
                'division' => $division->id,
                'district' => $district->id,
                'upazila' => $upazila->id,
                'union' => $union->id,
            ]))
            ->assertOk()
            ->assertJsonPath('counts.moderation', 1)
            ->assertJsonPath('counts.verify', 1)
            ->assertJsonPath('counts.photos', 1)
            ->assertJsonStructure(['html', 'counts']);
    }

    public function test_volunteer_sees_only_its_branch_and_the_child_selector_does_not_offer_other_branches(): void
    {
        $volunteer = $this->user('volunteer', '01700002003');
        $reporter = $this->user('citizen', '01700002004');
        [$division, $district, , $ownUnion] = $this->areaTree(' নিজের');
        $otherDistrict = AdminArea::create(['parent_id' => $division->id, 'level' => 'district', 'name_bn' => 'অন্য জেলা', 'name_en' => 'Other district']);
        $otherUpazila = AdminArea::create(['parent_id' => $otherDistrict->id, 'level' => 'upazila', 'name_bn' => 'অন্য উপজেলা', 'name_en' => 'Other upazila']);
        $otherUnion = AdminArea::create(['parent_id' => $otherUpazila->id, 'level' => 'union', 'name_bn' => 'অন্য ইউনিয়ন', 'name_en' => 'Other union']);
        $ownIssue = $this->issue($ownUnion, 'Only my union bridge');
        $otherIssue = $this->issue($otherUnion, 'Another district bridge');
        $this->queueData($ownIssue, $ownUnion, $reporter);
        $this->queueData($otherIssue, $otherUnion, $reporter);
        ModeratorArea::create(['user_id' => $volunteer->id, 'admin_area_id' => $ownUnion->id]);

        $this->actingAs($volunteer)->get(route('admin.work-center'))
            ->assertOk()
            ->assertSee('Only my union bridge')
            ->assertDontSee('Another division bridge')
            ->assertDontSee(__('workcenter.tab_issues'));

        $this->actingAs($volunteer)->getJson(route('admin.areas.children', $division))
            ->assertOk()
            ->assertJsonFragment(['id' => $district->id])
            ->assertJsonMissing(['id' => $otherDistrict->id]);
    }

    public function test_citizen_cannot_open_work_center(): void
    {
        $this->actingAs($this->user('citizen', '01700002005'))
            ->get(route('admin.work-center'))
            ->assertForbidden();
    }
}
