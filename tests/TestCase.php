<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Static per-request cache must not leak between tests in one process.
        \App\Support\AppearanceSettings::flush();
    }
}
