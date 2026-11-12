<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Support\IssuePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_matches_status(): void
    {
        $cases = [
            'reported' => 'new',
            'acknowledged' => 'new',
            'planned' => 'new',
            'work_started' => 'started',
            'completion_reported' => 'started',
            'repaired' => 'repaired',
        ];

        foreach ($cases as $status => $group) {
            $issue = new Issue();
            $issue->progress_status = $status;
            $this->assertSame($group, IssuePresenter::group($issue), $status);
        }
    }

    public function test_reopened_issue_counts_as_new(): void
    {
        $issue = new Issue();
        $issue->progress_status = 'repaired';
        $issue->disposition = 'reopened';

        $this->assertSame('new', IssuePresenter::group($issue));
    }

    public function test_home_shows_filter_chips(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="status-filter"', false)
            ->assertSee('data-filter="all"', false)
            ->assertSee('data-filter="new"', false)
            ->assertSee('data-filter="started"', false)
            ->assertSee('data-filter="repaired"', false);
    }

    public function test_filter_texts_exist_in_both_languages(): void
    {
        $keys = ['filter_label', 'filter_all', 'filter_new', 'filter_started', 'filter_repaired', 'filter_none', 'notified_tag', 'home_notified'];

        foreach (['en', 'bn'] as $locale) {
            app()->setLocale($locale);
            foreach ($keys as $key) {
                $this->assertNotSame('issues.' . $key, trans('issues.' . $key), $locale . ' ' . $key);
            }
        }

        app()->setLocale('bn');
        $this->assertMatchesRegularExpression('/[\x{0980}-\x{09FF}]/u', trans('issues.filter_all'));
    }
}