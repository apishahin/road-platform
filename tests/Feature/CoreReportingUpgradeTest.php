<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Issue;
use App\Models\User;
use App\Services\ModerationService;
use App\Support\AiProviderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreReportingUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'citizen'): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone' => '+88017' . random_int(10000000, 99999999),
            'role' => $role,
        ]));
    }

    private function taxonomy(): array
    {
        $parent = Category::create(['slug' => 'roads', 'name_bn' => 'সড়ক', 'name_en' => 'Roads', 'sort' => 1, 'is_active' => true, 'is_reportable' => false]);
        $pothole = Category::create(['parent_id' => $parent->id, 'slug' => 'pothole-test', 'name_bn' => 'গর্ত', 'name_en' => 'Pothole', 'sort' => 1, 'is_active' => true, 'is_reportable' => true]);
        $drain = Category::create(['parent_id' => $parent->id, 'slug' => 'drain-test', 'name_bn' => 'ড্রেন', 'name_en' => 'Drain', 'sort' => 2, 'is_active' => true, 'is_reportable' => true]);
        return [$parent, $pothole, $drain];
    }

    public function test_text_only_report_is_held_for_location_review_and_not_published_on_approval(): void
    {
        [, $category] = $this->taxonomy();
        $citizen = $this->user();

        $this->actingAs($citizen)->post('/report', [
            'location_precision' => 'text_only',
            'place_name' => 'মিরপুর ১০ গোলচত্বর',
            'location_note' => 'বাসস্ট্যান্ডের উত্তর পাশে',
            'category_id' => $category->id,
            'title' => 'বড় গর্ত',
            'description' => 'যানবাহন চলাচলে সমস্যা হচ্ছে।',
        ])->assertRedirect(route('report.success'));

        $issue = Issue::firstOrFail();
        $this->assertSame(Issue::LOCATION_REVIEW, $issue->location_review_status);
        $this->assertNull($issue->lat);
        $this->assertNull($issue->lng);
        $this->assertSame(0, Issue::active()->count());

        app(ModerationService::class)->approve($issue->reports()->firstOrFail(), $this->user('admin'));
        $this->assertNull($issue->fresh()->published_at);
    }

    public function test_staff_downgrading_a_public_issue_to_text_only_withdraws_it_from_direct_public_urls(): void
    {
        [, $category] = $this->taxonomy();
        $issue = Issue::create([
            'title' => 'Pinned issue', 'category_id' => $category->id, 'lat' => 23.8103, 'lng' => 90.4125,
            'progress_status' => 'reported', 'verification_status' => 'unverified', 'disposition' => 'active',
            'reports_count' => 1, 'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()])->save();

        $this->actingAs($this->user('admin'))->post(route('admin.issues.update', $issue), [
            'title' => 'Pinned issue',
            'location_precision' => 'text_only',
            'place_name' => 'Named landmark awaiting verification',
        ])->assertRedirect();

        $issue->refresh();
        $this->assertSame(Issue::LOCATION_REVIEW, $issue->location_review_status);
        $this->assertNull($issue->published_at);
        $this->assertNotNull($issue->unpublished_at);
        $this->actingAs($this->user())->get(route('issues.show', $issue))->assertNotFound();
    }

    public function test_nearby_lookup_only_returns_the_provisional_reportable_subcategory(): void
    {
        [, $pothole, $drain] = $this->taxonomy();
        foreach ([[$pothole, 'Pothole'], [$drain, 'Drain']] as [$category, $title]) {
            $issue = Issue::create([
                'title' => $title, 'category_id' => $category->id, 'lat' => 23.8103, 'lng' => 90.4125,
                'progress_status' => 'reported', 'verification_status' => 'unverified', 'disposition' => 'active',
                'reports_count' => 1, 'first_reported_at' => now(), 'last_activity_at' => now(),
            ]);
            $issue->forceFill(['published_at' => now()])->save();
        }

        $this->getJson('/api/v1/issues/nearby?lat=23.8103&lng=90.4125&radius=200&category_id=' . $pothole->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Pothole');
    }

    public function test_admin_ai_provider_key_is_encrypted_and_not_returned_to_the_form_configuration(): void
    {
        $admin = $this->user('admin');
        $secret = 'not-a-real-provider-secret';
        $this->actingAs($admin)->post('/admin/ai-reporting', [
            'enabled' => '1', 'daily_limit' => 20, 'per_user_hour_limit' => 2,
            'providers' => [[
                'label' => 'Free-tier test', 'type' => 'openai_compatible', 'model' => 'vision-test',
                'endpoint' => 'https://example.test/v1/chat/completions', 'api_key' => $secret,
                'enabled' => '1', 'priority' => 1, 'daily_limit' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        $stored = (string) DB::table('settings')->where('key', AiProviderSettings::KEY)->value('value');
        $this->assertStringNotContainsString($secret, $stored);
        $form = AiProviderSettings::forForm();
        $saved = collect($form['providers'])->firstWhere('label', 'Free-tier test');
        $this->assertNotNull($saved);
        $this->assertTrue($saved['has_api_key']);
        $this->assertArrayNotHasKey('api_key', $saved);
    }

    public function test_admin_can_save_a_ready_made_provider_with_only_an_api_key(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/admin/ai-reporting', [
            'enabled' => '1', 'daily_limit' => 20, 'per_user_hour_limit' => 2,
            'providers' => [[
                'preset' => 'gemini_flash',
                // Technical fields are deliberately omitted from a normal
                // preset submission and are assigned server-side.
                'api_key' => 'preset-secret', 'enabled' => '1',
            ]],
        ])->assertSessionHasNoErrors();

        $stored = json_decode((string) DB::table('settings')->where('key', AiProviderSettings::KEY)->value('value'), true);
        $provider = $stored['providers'][0];
        $this->assertSame('gemini_flash', $provider['preset']);
        $this->assertSame('gemini', $provider['type']);
        $this->assertSame('gemini-3.8-flash', $provider['model']);
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta', $provider['endpoint']);
        $this->assertStringNotContainsString('preset-secret', $provider['api_key']);

        $form = AiProviderSettings::forForm();
        $gemini = collect($form['providers'])->firstWhere('preset', 'gemini_flash');
        $this->assertTrue($gemini['has_api_key']);
        $this->assertTrue($gemini['enabled']);
    }

    public function test_preset_template_rows_do_not_drop_a_legacy_five_provider_configuration(): void
    {
        $admin = $this->user('admin');
        $custom = [];
        for ($i = 1; $i <= 5; $i++) {
            $custom[] = [
                'label' => 'Legacy provider ' . $i, 'type' => 'openai_compatible', 'model' => 'vision-' . $i,
                'endpoint' => 'https://example.test/' . $i . '/chat/completions', 'api_key' => 'key-' . $i,
                'enabled' => '1', 'priority' => $i, 'daily_limit' => 10,
            ];
        }
        $this->actingAs($admin)->post('/admin/ai-reporting', [
            'enabled' => '1', 'daily_limit' => 20, 'per_user_hour_limit' => 2, 'providers' => $custom,
        ])->assertSessionHasNoErrors();

        $resubmission = collect(AiProviderSettings::forForm()['providers'])->map(fn (array $provider) => [
            'id' => $provider['id'], 'preset' => $provider['preset'], 'label' => $provider['label'],
            'type' => $provider['type'], 'model' => $provider['model'], 'endpoint' => $provider['endpoint'],
            'enabled' => $provider['enabled'] ? '1' : null, 'priority' => $provider['priority'],
            'daily_limit' => $provider['daily_limit'],
        ])->all();
        $this->actingAs($admin)->post('/admin/ai-reporting', [
            'enabled' => '1', 'daily_limit' => 20, 'per_user_hour_limit' => 2, 'providers' => $resubmission,
        ])->assertSessionHasNoErrors();

        $stored = json_decode((string) DB::table('settings')->where('key', AiProviderSettings::KEY)->value('value'), true);
        $this->assertCount(5, $stored['providers']);
    }

    public function test_more_than_five_actual_providers_are_rejected_without_silent_data_loss(): void
    {
        $admin = $this->user('admin');
        $providers = [];
        for ($i = 1; $i <= 6; $i++) {
            $providers[] = [
                'label' => 'Provider ' . $i, 'type' => 'openai_compatible', 'model' => 'vision-' . $i,
                'endpoint' => 'https://example.test/' . $i . '/chat/completions', 'api_key' => 'key-' . $i,
                'enabled' => '1', 'priority' => $i, 'daily_limit' => 10,
            ];
        }
        $this->actingAs($admin)->from('/admin/ai-reporting')->post('/admin/ai-reporting', [
            'enabled' => '1', 'daily_limit' => 20, 'per_user_hour_limit' => 2, 'providers' => $providers,
        ])->assertRedirect('/admin/ai-reporting')->assertSessionHasErrors('providers');
        $this->assertNull(DB::table('settings')->where('key', AiProviderSettings::KEY)->value('value'));
    }

    public function test_admin_can_test_a_saved_provider_without_sending_a_citizen_photo(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/admin/ai-reporting', [
            'enabled' => '0', 'daily_limit' => 20, 'per_user_hour_limit' => 2,
            'providers' => [['preset' => 'gemini_flash', 'api_key' => 'probe-secret']],
        ])->assertSessionHasNoErrors();
        $stored = json_decode((string) DB::table('settings')->where('key', AiProviderSettings::KEY)->value('value'), true);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]],
            ]),
        ]);
        $this->actingAs($admin)->from('/admin/ai-reporting')->post(route('admin.ai-settings.test'), [
            'provider_id' => $stored['providers'][0]['id'],
        ])->assertRedirect('/admin/ai-reporting')->assertSessionHas('status');
    }

    public function test_manual_flow_requires_a_short_title_but_allows_an_empty_description(): void
    {
        [, $category] = $this->taxonomy();
        $citizen = $this->user();
        $payload = [
            'location_precision' => 'exact', 'lat' => 23.8103, 'lng' => 90.4125,
            'category_id' => $category->id,
        ];
        $this->actingAs($citizen)->from('/report')->post('/report', $payload + ['report_step' => 3])
            ->assertRedirect('/report')->assertSessionHasErrors('title')->assertSessionHasInput('report_step', 3);
        // A final-step validation error must return the citizen to that step,
        // instead of appearing to silently reset the whole report.
        $this->actingAs($citizen)->get('/report')->assertOk()
            ->assertSee('step: 3', false)
            ->assertSee('lat: "23.8103"', false)
            ->assertSee('lng: "90.4125"', false);

        $this->actingAs($citizen)->post('/report', $payload + ['title' => 'Damaged road surface'])
            ->assertRedirect(route('report.success'));
        $this->assertSame('Damaged road surface', Issue::firstOrFail()->title);
        $this->assertSame('', Issue::firstOrFail()->reports()->firstOrFail()->description);
    }

    public function test_three_step_report_ui_starts_with_an_interactive_bangladesh_location_map_and_finishes_with_photo_details(): void
    {
        $this->taxonomy();
        $response = $this->actingAs($this->user())->get('/report')->assertOk();

        $response->assertSeeInOrder([
            __('report.step1_heading'),
            __('report.step2_heading'),
            __('report.step3_heading'),
            'name="title"',
            'id="photos-main"',
        ], false);
        $response->assertSee('scrollWheelZoom: true', false)
            ->assertSee('touchZoom: true', false)
            ->assertSee('fitBangladesh()', false)
            ->assertSee('mapOptions.maxBoundsViscosity = 1', false)
            ->assertSee('flyTo([lat, lng]', false)
            ->assertSee('mainCategoryOpen: false', false)
            ->assertSee('chooseMain(group.id)', false)
            ->assertSee('requestAiSuggestion()', false)
            ->assertSee('validateFinalSubmission($event)', false)
            ->assertSee('if (this.descriptionWordCount() <= 150) return;', false)
            ->assertDontSee('openMapFullscreen()', false)
            ->assertDontSee('applyMapDisplay(this.map, L)', false)
            ->assertDontSee('mapEditing', false)
            ->assertDontSee('location_set_or_correct', false)
            ->assertDontSee("step===4", false);
    }

    public function test_description_is_optional_but_capped_at_150_words(): void
    {
        [, $category] = $this->taxonomy();
        $citizen = $this->user();
        $tooLong = implode(' ', array_fill(0, 151, 'word'));
        $this->actingAs($citizen)->from('/report')->post('/report', [
            'location_precision' => 'exact', 'lat' => 23.8103, 'lng' => 90.4125,
            'category_id' => $category->id, 'title' => 'Too long description test', 'description' => $tooLong,
        ])->assertRedirect('/report')->assertSessionHasErrors('description');
    }

    public function test_admin_can_sync_models_for_a_saved_provider_and_choose_an_override(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/admin/ai-reporting', [
            'enabled' => '0', 'daily_limit' => 20, 'per_user_hour_limit' => 2, 'default_provider_index' => 0,
            'providers' => [[
                'preset' => 'gemini_flash', 'api_key' => 'model-sync-secret',
                'model' => 'gemini-account-vision', 'enabled' => '1',
            ]],
        ])->assertSessionHasNoErrors();
        $stored = json_decode((string) DB::table('settings')->where('key', AiProviderSettings::KEY)->value('value'), true);
        $provider = $stored['providers'][0];
        $this->assertSame('gemini-account-vision', $provider['model_override']);
        $this->assertTrue($provider['is_default']);
        $this->assertSame('gemini-account-vision', AiProviderSettings::providerForTest($provider['id'])['model']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'models' => [['name' => 'models/gemini-account-vision'], ['name' => 'models/gemini-other']],
            ]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.ai-settings.models'), ['provider_id' => $provider['id']])
            ->assertOk()->assertJsonPath('data.0', 'gemini-account-vision');
    }

    public function test_admin_can_test_the_enabled_fallback_chain_without_a_citizen_photo(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/admin/ai-reporting', [
            'enabled' => '0', 'daily_limit' => 20, 'per_user_hour_limit' => 2,
            'providers' => [['preset' => 'gemini_flash', 'api_key' => 'chain-secret', 'enabled' => '1']],
        ])->assertSessionHasNoErrors();
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]],
            ]),
        ]);
        $this->actingAs($admin)->from('/admin/ai-reporting')->post(route('admin.ai-settings.test-chain'))
            ->assertRedirect('/admin/ai-reporting')->assertSessionHas('status');
        $this->assertSame('success', AiProviderSettings::forForm()['providers'][0]['last_test_status']);
    }
}
