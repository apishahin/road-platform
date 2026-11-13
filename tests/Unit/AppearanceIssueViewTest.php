<?php

namespace Tests\Unit;

use App\Support\AppearanceSettings;
use PHPUnit\Framework\TestCase;

class AppearanceIssueViewTest extends TestCase
{
    public function test_default_is_simple(): void
    {
        $this->assertSame('simple', AppearanceSettings::defaults()['issue_view']);
        $this->assertSame('simple', AppearanceSettings::sanitize([])['issue_view']);
    }

    public function test_detailed_is_accepted(): void
    {
        $this->assertSame('detailed', AppearanceSettings::sanitize(['issue_view' => 'detailed'])['issue_view']);
    }

    public function test_bad_value_falls_back_to_simple(): void
    {
        $this->assertSame('simple', AppearanceSettings::sanitize(['issue_view' => '<script>'])['issue_view']);
        $this->assertSame('simple', AppearanceSettings::sanitize(['issue_view' => ['x']])['issue_view']);
    }
}