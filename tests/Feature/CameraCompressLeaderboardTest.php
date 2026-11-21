<?php

namespace Tests\Feature;

use App\Models\{AdminArea, Category, Issue, Observation, Report, User};
use App\Support\UpdatePhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CameraCompressLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'citizen', ?string $name = null): User
    {
        return User::unguarded(fn () => User::create([
            'public_id'    => (string) Str::ulid(),
            'phone'        => '0171' . random_int(1000000, 9999999),
            'role'         => $role,
            'display_name' => $name,
        ]));
    }

    private function makeIssue(): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'cc-cat'],
            ['name_bn' => 'CC', 'name_en' => 'CC', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $issue = Issue::create([
            'title' => 'CC issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    public function test_report_page_has_camera_button_and_compression(): void
    {
        $res = $this->actingAs($this->user())->get('/report');
        $res->assertOk()
            ->assertSee('data-compress', false)
            ->assertSee('data-camera-for="photos-main"', false)
            ->assertSee('capture="environment"', false)
            ->assertSee('createImageBitmap', false);
    }

    public function test_issue_page_photo_inputs_compress_when_updates_enabled(): void
    {
        UpdatePhotos::set(true);
        $issue = $this->makeIssue();

        $this->actingAs($this->user())->get(route('issues.show', $issue->id))
            ->assertOk()
            ->assertSee('data-compress', false)
            ->assertSee('photo_camera', false);
    }

    public function test_leaderboard_is_public_and_orders_by_score(): void
    {
        $issue = $this->makeIssue();

        $alice = $this->user('citizen', 'Alice Rahman');
        $bob   = $this->user('citizen', 'Bob Karim');

        // Alice: 2 approved reports (6 pts); Bob: 1 observation (1 pt)
        foreach (range(1, 2) as $i) {
            Report::unguarded(fn () => Report::create([
                'public_id' => (string) Str::ulid(), 'issue_id' => $issue->id, 'user_id' => $alice->id,
                'description' => 'd', 'lat' => 23.8, 'lng' => 90.4, 'moderation' => 'approved',
            ]));
        }
        Report::unguarded(fn () => Report::create([
            'public_id' => (string) Str::ulid(), 'issue_id' => $issue->id, 'user_id' => $bob->id,
            'description' => 'd', 'lat' => 23.8, 'lng' => 90.4, 'moderation' => 'pending', // not counted
        ]));
        Observation::unguarded(fn () => Observation::create([
            'issue_id' => $issue->id, 'user_id' => $bob->id, 'type' => 'still_exists',
        ]));

        $res = $this->get(route('leaderboard'));
        $res->assertOk()->assertSee('data-leaderboard', false);
        $html = $res->getContent();
        $this->assertTrue(strpos($html, 'Alice Rahman') < strpos($html, 'Bob Karim'));
        $res->assertSee('Alice Rahman')->assertSee('Bob Karim');
        // phones never leak
        $res->assertDontSee($alice->phone)->assertDontSee($bob->phone);
    }

    public function test_leaderboard_shows_my_standing_when_logged_in(): void
    {
        $issue = $this->makeIssue();
        $me    = $this->user('citizen', 'Me Myself');
        Observation::unguarded(fn () => Observation::create([
            'issue_id' => $issue->id, 'user_id' => $me->id, 'type' => 'fixed',
        ]));

        $this->actingAs($me)->get(route('leaderboard'))
            ->assertOk()
            ->assertSee('data-leaderboard-me', false)
            ->assertSee('Me Myself');
    }

    protected function tearDown(): void
    {
        UpdatePhotos::set(false);
        parent::tearDown();
    }
}
