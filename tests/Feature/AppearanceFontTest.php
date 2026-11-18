<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppearanceFontTest extends TestCase
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

    private function setFont(string $font): void
    {
        $d = \App\Support\AppearanceSettings::defaults();
        $this->actingAs($this->admin())->post('/admin/appearance', [
            'font'        => $font,
            'size'        => 'normal',
            'brand'       => $d['brand'],
            'header_bg'   => $d['header_bg'],
            'header_text' => $d['header_text'],
        ])->assertRedirect();
    }

    public function test_font_choice_reaches_the_whole_page_via_body_rule(): void
    {
        $this->setFont('hind_google');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("--font-sans:'Hind Siliguri'", $html);
        // body rule makes the choice immune to stale compiled assets
        $this->assertStringContainsString('body{font-family:var(--font-sans)', $html);
    }

    public function test_default_font_is_noto_sans_bengali_self_hosted(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // default = Noto Sans Bengali, loaded from our own server (no CDN)
        $this->assertStringContainsString('fonts/noto-sans-bengali.css', $html);
        $this->assertStringContainsString("--font-sans:'Noto Sans Bengali'", $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
    }

    public function test_hind_siliguri_is_served_from_local_stylesheet(): void
    {
        $this->setFont('hind_google');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('fonts/hind-siliguri.css', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_system_font_loads_no_webfont_stylesheet(): void
    {
        $this->setFont('system');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts/noto-sans-bengali.css', $html);
        $this->assertStringNotContainsString('fonts/hind-siliguri.css', $html);
    }

    public function test_system_font_means_real_system_stack(): void
    {
        $this->setFont('system');

        $html = $this->get('/')->assertOk()->getContent();

        // must NOT sneak Noto back in — system means the visitor's OS font
        $this->assertStringNotContainsString("--font-sans:'Noto Sans Bengali'", $html);
        $this->assertStringContainsString('Nirmala UI', $html);
    }
}
