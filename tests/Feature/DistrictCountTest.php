<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use App\Support\SiteSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictCountTest extends TestCase
{
    use RefreshDatabase;

    private function makeIssue(int $areaId, string $disposition = 'active'): Issue
    {
        $cat = Category::unguarded(fn () => Category::firstOrCreate(
            ['slug' => 'dc-cat'],
            ['name_bn' => 'DC', 'name_en' => 'DC', 'sort' => 1, 'is_active' => 1]
        ));
        $issue = Issue::create([
            'title' => 'DC issue', 'category_id' => $cat->id,
            'lat' => 23.8, 'lng' => 90.4, 'admin_area_id' => $areaId,
            'progress_status' => 'reported', 'verification_status' => 'unverified',
            'disposition' => $disposition, 'reports_count' => 1,
            'first_reported_at' => now(), 'last_activity_at' => now(),
        ]);
        $issue->forceFill(['published_at' => now()->toDateTimeString()])->save();

        return $issue;
    }

    public function test_counts_districts_that_have_active_reports(): void
    {
        $this->assertSame(0, Issue::activeDistrictCount());

        $distA  = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ক', 'name_en' => 'A']);
        $upzA1  = AdminArea::create(['parent_id' => $distA->id, 'level' => 'upazila', 'name_bn' => 'ক১', 'name_en' => 'A1']);
        $upzA2  = AdminArea::create(['parent_id' => $distA->id, 'level' => 'upazila', 'name_bn' => 'ক২', 'name_en' => 'A2']);
        $distB  = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'খ', 'name_en' => 'B']);

        // two issues inside district A (via upazilas) still count as ONE district
        $this->makeIssue($upzA1->id);
        $this->makeIssue($upzA2->id);
        $this->assertSame(1, Issue::activeDistrictCount());

        // an issue pinned directly at district level counts too
        $this->makeIssue($distB->id);
        $this->assertSame(2, Issue::activeDistrictCount());

        // rejected issues do not count
        $this->makeIssue($distB->id, 'rejected');
        $this->assertSame(2, Issue::activeDistrictCount());
    }

    public function test_ribbon_shows_auto_district_count_and_override_still_wins(): void
    {
        $dist = AdminArea::create(['parent_id' => null, 'level' => 'district', 'name_bn' => 'ক', 'name_en' => 'A']);
        $this->makeIssue($dist->id);

        SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'm1' => false, 'm2' => false, 'm3' => false, 'm4' => true]]]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-stats-ribbon', $html);
        $this->assertMatchesRegularExpression('/<strong>\s*' . \App\Support\IssuePresenter::num(1) . '\s*<\/strong>/u', $html);

        // admin override still takes precedence
        SiteSections::save(['sections' => ['ribbon' => ['on' => true, 'm1' => false, 'm2' => false, 'm3' => false, 'm4' => true, 'district_n' => 'ZZ64']]]);
        $this->get('/')->assertOk()->assertSee('ZZ64');
    }
}
