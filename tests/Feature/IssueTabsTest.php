<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\Category;
use App\Models\Issue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueTabsTest extends TestCase
{
    use RefreshDatabase;

    private function mk(string $title, string $progress, ?int $areaId = null): Issue
    {
        $cat = Category::firstOrCreate(['slug' => 'test-cat'], [
            'name_bn' => 'টেস্ট', 'name_en' => 'Test', 'sort' => 1, 'is_active' => 1,
        ]);
        $issue = Issue::create([
            'title'               => $title,
            'category_id'         => $cat->id,
            'lat'                 => 24.5700000,
            'lng'                 => 90.4000000,
            'progress_status'     => $progress,
            'verification_status' => $progress === 'repaired' ? 'verified' : 'unverified',
            'disposition'         => 'active',
            'reports_count'       => 1,
            'first_reported_at'   => now(),
            'last_activity_at'    => now(),
        ]);
        $fill = ['published_at' => now()->toDateTimeString()];
        if ($areaId) {
            $fill['admin_area_id'] = $areaId;
        }
        $issue->forceFill($fill)->save();

        return $issue;
    }

    private function area(string $name): AdminArea
    {
        return AdminArea::unguarded(fn () => AdminArea::create(['level' => 'upazila', 'name_bn' => $name]));
    }

    public function test_tabs_filter_by_repaired_state(): void
    {
        $this->mk('ZZOPEN One', 'reported');
        $this->mk('ZZFIXED One', 'repaired');

        $this->get('/issues?tab=repaired')->assertOk()
            ->assertSee('ZZFIXED One')->assertDontSee('ZZOPEN One');
        $this->get('/issues?tab=open')->assertOk()
            ->assertSee('ZZOPEN One')->assertDontSee('ZZFIXED One');
        $this->get('/issues')->assertOk()
            ->assertSee('ZZOPEN One')->assertSee('ZZFIXED One');
    }

    public function test_invalid_tab_is_ignored(): void
    {
        $this->mk('ZZOPEN Two', 'reported');
        $this->mk('ZZFIXED Two', 'repaired');

        $this->get('/issues?tab=bogus')->assertOk()
            ->assertSee('ZZOPEN Two')->assertSee('ZZFIXED Two');
    }

    public function test_chosen_status_wins_over_tab(): void
    {
        $this->mk('ZZOPEN Three', 'reported');
        $this->mk('ZZFIXED Three', 'repaired');

        $this->get('/issues?tab=repaired&status=reported')->assertOk()
            ->assertSee('ZZOPEN Three')->assertDontSee('ZZFIXED Three');
    }

    public function test_tab_works_with_area_filter(): void
    {
        $a = $this->area('এলাকা-ক');
        $b = $this->area('এলাকা-খ');
        $this->mk('ZZFIXED InA', 'repaired', $a->id);
        $this->mk('ZZFIXED InB', 'repaired', $b->id);
        $this->mk('ZZOPEN InA', 'reported', $a->id);

        $this->get('/issues?tab=repaired&area=' . $a->id)->assertOk()
            ->assertSee('ZZFIXED InA')
            ->assertDontSee('ZZFIXED InB')
            ->assertDontSee('ZZOPEN InA');
    }

    public function test_tab_links_are_shown(): void
    {
        $this->get('/issues')->assertOk()
            ->assertSee('tab=open', false)
            ->assertSee('tab=repaired', false);
    }

    public function test_tab_bar_and_results_have_swap_hooks(): void
    {
        $this->mk('ZZOPEN Hook', 'reported');

        $this->get('/issues?tab=open')->assertOk()
            ->assertSee('id="tabs-bar"', false)
            ->assertSee('id="issue-results"', false)
            ->assertSee('id="tab-field"', false);
    }
}
