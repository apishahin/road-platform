<?php

namespace Tests\Feature;

use App\Models\{AdminArea, Category, Issue, User};
use App\Support\ApiKeySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicApiTest extends TestCase
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

    private function makeIssue(): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'api-cat'],
            ['name_bn' => 'API', 'name_en' => 'API', 'sort' => 1, 'is_active' => 1]
        ));
        $area = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka']);
        $issue = Issue::create([
            'title' => 'API issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $area->id,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => 'active', 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    public function test_public_endpoints_return_json(): void
    {
        $issue = $this->makeIssue();

        $this->getJson('/api/v1/issues')->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
        $this->getJson('/api/v1/issues/' . $issue->public_id)->assertOk()
            ->assertJsonPath('data.title', 'API issue')
            ->assertJsonPath('data.status.progress', 'reported')
            ->assertJsonStructure(['data' => ['photos']]);
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.slug', 'api-cat');
        $this->getJson('/api/v1/areas?level=district')->assertOk()->assertJsonPath('data.0.name_en', 'Dhaka');
        $this->getJson('/api/v1/stats')->assertOk()->assertJsonPath('data.issues_total', 1);
    }

    public function test_issues_filter_by_status(): void
    {
        $this->makeIssue();

        $this->getJson('/api/v1/issues?status=repaired')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/issues?status=reported')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/issues?category=nope')->assertJsonPath('meta.total', 0);
    }

    public function test_turning_off_public_read_requires_key(): void
    {
        ApiKeySettings::savePublicRead(false);
        $this->getJson('/api/v1/stats')->assertStatus(401)->assertJsonPath('error.code', 'api_key_required');

        [$row, $plain] = ApiKeySettings::createKey('Test partner');
        $this->getJson('/api/v1/stats', ['X-Api-Key' => $plain])->assertOk();
        $this->getJson('/api/v1/stats', ['X-Api-Key' => 'rp_wrong'])->assertStatus(401);

        ApiKeySettings::revoke($row['id']);
        $this->getJson('/api/v1/stats', ['X-Api-Key' => $plain])->assertStatus(401);
    }

    public function test_admin_manages_keys_and_shows_once(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.api.index'))->assertOk()->assertSee(__('issues.api_no_keys'));

        $res = $this->actingAs($admin)->post(route('admin.api.keys.store'), ['label' => 'News site']);
        $res->assertRedirect();
        $this->assertCount(1, ApiKeySettings::all()['keys']);

        // the plaintext flashes exactly once
        $this->actingAs($admin)->get(route('admin.api.index'))->assertOk()->assertSee('rp_');

        $id = ApiKeySettings::all()['keys'][0]['id'];
        $this->actingAs($admin)->delete(route('admin.api.keys.destroy', $id))->assertRedirect();
        $this->assertCount(0, ApiKeySettings::all()['keys']);
    }

    public function test_developers_page_renders(): void
    {
        $this->get(route('developers'))->assertOk()->assertSee('/api/v1/issues', false);
    }
}
