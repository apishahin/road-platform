<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_carries_baseline_security_headers(): void
    {
        $res = $this->get('/')->assertOk();

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('geolocation=(self)', $res->headers->get('Permissions-Policy'));
    }
}
