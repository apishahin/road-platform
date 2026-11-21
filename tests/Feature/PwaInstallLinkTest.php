<?php

namespace Tests\Feature;

use App\Support\QrCodeSvg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PwaInstallLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_install_landing_page_has_native_install_and_share_controls(): void
    {
        $this->get(route('pwa.install'))
            ->assertOk()
            ->assertSee('data-install-page', false)
            ->assertSee('data-install-primary', false)
            ->assertSee('data-install-copy', false)
            ->assertSee(route('pwa.install.qr'), false)
            ->assertSee(__('pwa.install_android_title'))
            ->assertSee(__('pwa.install_ios_title'));
    }

    public function test_install_qr_is_a_same_origin_svg(): void
    {
        $response = $this->get(route('pwa.install.qr'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertSee('<svg', false)
            ->assertSee('role="img"', false)
            ->assertSee('shape-rendering="crispEdges"', false);

        $this->assertStringContainsString('<path d="M', $response->getContent());
    }

    public function test_qr_matrix_uses_a_valid_size_for_short_install_urls(): void
    {
        $matrix = QrCodeSvg::matrix('https://example.test/install');

        $this->assertCount(25, $matrix); // version 2: 17 + 4×2 modules
        $this->assertCount(25, $matrix[0]);
    }

    public function test_qr_rejects_a_payload_beyond_its_public_link_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QrCodeSvg::matrix('https://example.test/install?' . str_repeat('a', 280));
    }
}
