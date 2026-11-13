<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Evidence;
use App\Models\Issue;
use App\Models\ModeratorArea;
use App\Models\User;
use App\Support\StorageSettings;
use App\Support\UpdatePhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdatePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(StorageSettings::privateDisk());
        Storage::fake(StorageSettings::publicDisk());
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

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('p.jpg', 640, 480);
    }

    private function observe(User $u, Issue $i, array $extra = [])
    {
        return $this->actingAs($u)->post('/issues/' . $i->public_id . '/observe', array_merge(['type' => 'fixed'], $extra));
    }

    private function pendingPhoto(Issue $issue, string $phone = '01711000001'): Evidence
    {
        UpdatePhotos::set(true);
        $citizen = $this->makeUser($phone, 'citizen');
        $this->observe($citizen, $issue, ['photo_gallery' => $this->photo()]);

        return Evidence::whereNotNull('observation_id')->orderByDesc('id')->firstOrFail();
    }

    public function test_switch_is_off_by_default_and_photo_is_ignored(): void
    {
        $issue = $this->makeIssue();
        $citizen = $this->makeUser('01711000001', 'citizen');

        $this->assertFalse(UpdatePhotos::enabled());
        $this->observe($citizen, $issue, ['photo_gallery' => $this->photo()])->assertRedirect();

        $this->assertSame(1, $issue->observations()->count());
        $this->assertSame(0, Evidence::count());
    }

    public function test_photo_box_follows_the_switch(): void
    {
        $issue = $this->makeIssue();
        $citizen = $this->makeUser('01711000001', 'citizen');

        $this->actingAs($citizen)->get('/issues/' . $issue->public_id)
            ->assertOk()->assertDontSee(__('issues.photo_camera'));

        UpdatePhotos::set(true);
        $this->actingAs($citizen)->get('/issues/' . $issue->public_id)
            ->assertOk()->assertSee(__('issues.photo_camera'));
    }

    public function test_citizen_photo_waits_and_is_not_public(): void
    {
        $issue = $this->makeIssue();
        $ev = $this->pendingPhoto($issue);

        $this->assertSame('pending', $ev->moderation);
        $this->assertNotNull($ev->observation_id);
        $this->assertSame(0, UpdatePhotos::publicFor($issue)->count());

        auth()->logout();
        $this->get('/issues/' . $issue->public_id)->assertOk()
            ->assertDontSee(__('issues.photos_updates_title'));
    }

    public function test_approved_photo_is_shown_on_issue_page(): void
    {
        $issue = $this->makeIssue('Photo issue', $area = $this->makeArea('Area A'));
        $ev = $this->pendingPhoto($issue);
        $vol = $this->volunteerIn($area, '01722000001');

        $this->actingAs($vol)->post(route('photoreview.approve', $ev))->assertRedirect();

        $this->assertSame('approved', $ev->fresh()->moderation);
        $this->assertSame(1, UpdatePhotos::publicFor($issue)->count());

        auth()->logout();
        $this->get('/issues/' . $issue->public_id)->assertOk()
            ->assertSee(__('issues.photos_updates_title'))
            ->assertSee(__('issues.photo_cap_citizen'));
    }

    public function test_hidden_photo_is_never_public(): void
    {
        $issue = $this->makeIssue();
        $ev = $this->pendingPhoto($issue);
        $mod = $this->makeUser('01733000001', 'moderator');

        $this->actingAs($mod)->post(route('photoreview.hide', $ev), ['reason' => 'face'])->assertRedirect();

        $this->assertNotNull($ev->fresh()->hidden_at);
        $this->assertSame(0, UpdatePhotos::publicFor($issue)->count());

        $this->actingAs($mod)->post(route('photoreview.approve', $ev))->assertStatus(404);
    }

    public function test_citizen_and_guest_cannot_review(): void
    {
        $issue = $this->makeIssue();
        $ev = $this->pendingPhoto($issue);
        $other = $this->makeUser('01711000009', 'citizen');

        $this->actingAs($other)->get(route('photoreview.index'))->assertStatus(403);
        $this->actingAs($other)->post(route('photoreview.approve', $ev))->assertStatus(403);
        $this->actingAs($other)->get(route('photoreview.image', $ev))->assertStatus(403);

        auth()->logout();
        $this->get(route('photoreview.index'))->assertStatus(302);
        $this->assertSame('pending', $ev->fresh()->moderation);
    }

    public function test_volunteer_outside_area_cannot_decide(): void
    {
        $area = $this->makeArea('Area A');
        $other = $this->makeArea('Area B');
        $issue = $this->makeIssue('Issue in A', $area);
        $ev = $this->pendingPhoto($issue);
        $outsider = $this->volunteerIn($other, '01722000002');

        $this->actingAs($outsider)->post(route('photoreview.approve', $ev))->assertStatus(403);
        $this->actingAs($outsider)->post(route('photoreview.hide', $ev), ['reason' => 'face'])->assertStatus(403);
        $this->actingAs($outsider)->get(route('photoreview.image', $ev))->assertStatus(403);
        $this->assertSame('pending', $ev->fresh()->moderation);
    }

    public function test_review_page_lists_only_own_area_and_shows_issue_number(): void
    {
        $a = $this->makeArea('Area A');
        $b = $this->makeArea('Area B');
        $issueA = $this->makeIssue('Mine in A', $a);
        $issueB = $this->makeIssue('Theirs in B', $b);
        $this->pendingPhoto($issueA, '01711000011');
        $this->pendingPhoto($issueB, '01711000012');
        $vol = $this->volunteerIn($a, '01722000003');

        $this->actingAs($vol)->get(route('photoreview.index'))->assertOk()
            ->assertSee('Mine in A')
            ->assertSee('#' . $issueA->id)
            ->assertDontSee('Theirs in B');
    }

    public function test_at_most_three_photos_per_update(): void
    {
        UpdatePhotos::set(true);
        $issue = $this->makeIssue();
        $citizen = $this->makeUser('01711000001', 'citizen');

        for ($i = 0; $i < 4; $i++) {
            $this->observe($citizen, $issue, ['photo_gallery' => $this->photo()]);
        }

        $this->assertSame(3, Evidence::count());
        $this->assertSame(1, $issue->observations()->count());
    }

    public function test_site_visit_photo_is_linked_to_the_status_event(): void
    {
        UpdatePhotos::set(true);
        $area = $this->makeArea('Area A');
        $issue = $this->makeIssue('Visit issue', $area);
        $vol = $this->volunteerIn($area, '01722000004');

        $this->actingAs($vol)->post('/issues/' . $issue->public_id . '/verify', [
            'type' => 'repaired', 'note' => 'I saw the road repaired', 'seen' => '1',
            'photo_camera' => $this->photo(),
        ])->assertRedirect();

        $ev = Evidence::firstOrFail();
        $this->assertNotNull($ev->status_event_id);
        $this->assertNull($ev->observation_id);
        $this->assertSame('pending', $ev->moderation);
        $this->assertSame('repaired', $issue->fresh()->progress_status);
    }

    public function test_covering_deletes_the_clear_original(): void
    {
        $issue = $this->makeIssue();
        $ev = $this->pendingPhoto($issue);
        $mod = $this->makeUser('01733000002', 'moderator');
        $original = $ev->private_path;
        $this->assertNotEmpty($original);

        $rects = json_encode([['x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.3, 'm' => 'black']]);
        $this->actingAs($mod)->post(route('photoreview.blur', $ev), ['rects' => $rects])->assertRedirect();

        $this->assertNull($ev->fresh()->private_path);
        Storage::disk(StorageSettings::privateDisk())->assertMissing($original);
        $this->assertSame('pending', $ev->fresh()->moderation);
    }

    public function test_covering_rejects_bad_input_and_outsiders(): void
    {
        $area = $this->makeArea('Area A');
        $issue = $this->makeIssue('Cover issue', $area);
        $ev = $this->pendingPhoto($issue);
        $mod = $this->makeUser('01733000003', 'moderator');
        $outsider = $this->volunteerIn($this->makeArea('Area B'), '01722000005');

        $this->actingAs($mod)->post(route('photoreview.blur', $ev), ['rects' => 'not json'])->assertStatus(422);
        $this->actingAs($mod)->post(route('photoreview.blur', $ev), ['rects' => '[]'])->assertStatus(422);

        $rects = json_encode([['x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.3]]);
        $this->actingAs($outsider)->post(route('photoreview.blur', $ev), ['rects' => $rects])->assertStatus(403);
        $this->assertNotNull($ev->fresh()->private_path);
    }

    public function test_only_admin_can_change_the_switch(): void
    {
        $citizen = $this->makeUser('01711000001', 'citizen');
        $mod = $this->makeUser('01733000004', 'moderator');
        $admin = $this->makeUser('01700000001', 'admin');

        $this->actingAs($citizen)->post(route('photoreview.toggle'), ['enabled' => 1])->assertStatus(403);
        $this->actingAs($mod)->post(route('photoreview.toggle'), ['enabled' => 1])->assertStatus(403);
        $this->assertFalse(UpdatePhotos::enabled());

        $this->actingAs($admin)->post(route('photoreview.toggle'), ['enabled' => 1])->assertRedirect();
        $this->assertTrue(UpdatePhotos::enabled());

        $this->actingAs($admin)->post(route('photoreview.toggle'), ['enabled' => 0])->assertRedirect();
        $this->assertFalse(UpdatePhotos::enabled());
    }
}