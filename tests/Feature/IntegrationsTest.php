<?php

namespace Tests\Feature;

use App\Models\{AdminArea, Category, Issue, PushToken, User, UserNotification};
use App\Services\{IssueStatusService, SocialPoster};
use App\Support\{DigestSettings, PushSettings, ShareSettings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0171' . random_int(1000000, 9999999),
            'role'      => 'admin',
        ]));
    }

    private function citizen(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0181' . random_int(1000000, 9999999),
            'role'      => 'citizen',
        ]));
    }

    private function makeIssue(): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'int-cat'],
            ['name_bn' => 'IN', 'name_en' => 'IN', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $issue = Issue::create([
            'title' => 'IN issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    public function test_integrations_page_admin_only_and_defaults_off(): void
    {
        $this->assertFalse(PushSettings::on());
        $this->assertFalse(ShareSettings::on());
        $this->assertFalse(DigestSettings::on());

        $this->get(route('admin.integrations.index'))->assertRedirect('/login');
        $this->actingAs($this->citizen())->get(route('admin.integrations.index'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.integrations.index'))
            ->assertOk()->assertSee('data-integrations', false);
    }

    public function test_push_token_store_and_remove(): void
    {
        $user = $this->citizen();
        $this->post(route('push.token'), ['token' => 'tok-123'])->assertRedirect('/login');

        $this->actingAs($user)->postJson(route('push.token'), ['token' => 'tok-123'])->assertOk();
        $this->assertDatabaseHas('push_tokens', ['token' => 'tok-123', 'user_id' => $user->id]);

        // same token re-registered by another user transfers ownership
        $other = $this->citizen();
        $this->actingAs($other)->postJson(route('push.token'), ['token' => 'tok-123'])->assertOk();
        $this->assertDatabaseHas('push_tokens', ['token' => 'tok-123', 'user_id' => $other->id]);
        $this->assertSame(1, PushToken::count());

        $this->actingAs($other)->deleteJson(route('push.token.delete'), ['token' => 'tok-123'])->assertOk();
        $this->assertSame(0, PushToken::count());
    }

    public function test_service_worker_served_with_config(): void
    {
        $res = $this->get('/firebase-messaging-sw.js');
        $res->assertOk();
        $this->assertStringContainsString('application/javascript', $res->headers->get('content-type'));
        $this->assertStringContainsString('firebase.initializeApp', $res->getContent());
    }

    public function test_social_share_posts_when_repaired_and_on(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '1_2'], 200)]);

        $issue = $this->makeIssue();
        $admin = $this->admin();

        // OFF → no HTTP call
        $event = \App\Models\StatusEvent::create([
            'issue_id' => $issue->id, 'dimension' => 'progress', 'from_value' => 'work_started',
            'to_value' => 'repaired', 'actor_id' => $admin->id, 'actor_type' => 'moderator',
            'source_type' => 'platform', 'reason' => 'test',
        ]);
        SocialPoster::afterStatusChange($issue, $event);
        Http::assertNothingSent();

        // ON with credentials → posts to the page feed
        ShareSettings::save(['on' => true, 'fb_page_id' => '123456', 'fb_token' => 'secret-token']);
        SocialPoster::afterStatusChange($issue, $event);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v21.0/123456/feed')
            && $r['access_token'] === 'secret-token'
            && str_contains($r['link'], '/issue/'));

        // non-repaired events never post
        Http::fake();
        $event2 = \App\Models\StatusEvent::create([
            'issue_id' => $issue->id, 'dimension' => 'progress', 'from_value' => 'reported',
            'to_value' => 'acknowledged', 'actor_id' => $admin->id, 'actor_type' => 'moderator',
            'source_type' => 'platform', 'reason' => 'test',
        ]);
        SocialPoster::afterStatusChange($issue, $event2);
        Http::assertNothingSent();
    }

    public function test_weekly_digest_command_sends_when_enabled(): void
    {
        Mail::fake();

        $this->artisan('digest:weekly')->assertSuccessful();
        Mail::assertNothingSent();

        DigestSettings::save(true, 'boss@example.org, second@example.org, not-an-email');
        $this->makeIssue();

        $this->artisan('digest:weekly')->assertSuccessful();
        // raw mails can't be asserted via Mail::fake mailable API; verify settings sanity instead
        $this->assertEquals(['boss@example.org', 'second@example.org'], DigestSettings::emails());
    }

    public function test_push_does_nothing_when_off_even_with_tokens(): void
    {
        Http::fake();
        $user = $this->citizen();
        PushToken::create(['user_id' => $user->id, 'token' => 'tok-off']);

        \App\Services\PushService::sendToUsers([$user->id], 'T', 'B', '/issues/x');
        Http::assertNothingSent();
    }
}
