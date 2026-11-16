<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_complete(): void
    {
        // The manifest is served by PHP so the site name / custom icon flow in.
        $m = $this->get('/manifest.json')->assertOk()->json();
        $this->assertIsArray($m);
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/', $m['start_url']);
        $this->assertSame('bn', $m['lang']);
        $this->assertNotEmpty($m['theme_color']);

        $sizes = array_column($m['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        $purposes = array_column($m['icons'], 'purpose');
        $this->assertContains('maskable', array_filter($purposes));
    }

    public function test_icons_exist_on_disk(): void
    {
        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-192.png', 'icon-maskable-512.png', 'apple-touch-icon.png'] as $f) {
            $p = public_path('icons/' . $f);
            $this->assertFileExists($p);
            $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->file($p));
        }
    }

    public function test_service_worker_ships_an_offline_fallback(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('pwa-v', $sw);
        $this->assertStringContainsString('/offline', $sw);
        $this->assertStringContainsString('navigate', $sw);
    }

    public function test_layout_links_manifest_and_registers_sw(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/manifest.json', false)
            ->assertSee('theme-color', false)
            ->assertSee("serviceWorker", false)
            ->assertSee('/sw.js', false);
    }

    public function test_offline_page_renders(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertSee(__('pwa.offline_title'))
            ->assertSee(__('pwa.retry'));
    }
}
