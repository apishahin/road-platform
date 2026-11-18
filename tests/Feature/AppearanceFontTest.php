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

    public function test_system_font_means_real_system_stack(): void
    {
        $this->setFont('system');

        $html = $this->get('/')->assertOk()->getContent();

        // must NOT sneak Noto back in — system means the visitor's OS font
        $this->assertStringNotContainsString("--font-sans:'Noto Sans Bengali'", $html);
        $this->assertStringContainsString('Nirmala UI', $html);
    }
}
