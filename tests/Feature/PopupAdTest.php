<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class PopupAdTest extends TestCase
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

    public function test_popup_is_off_by_default(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-rp-popup', false);
    }

    public function test_admin_can_enable_text_popup_with_link_and_timing(): void
    {
        $this->actingAs($this->admin())->post('/admin/site', [
            'popup_on'   => '1',
            'popup_text' => 'Eid schedule notice',
            'popup_link' => 'https://example.com/offer',
            'popup_delay' => '5',
            'popup_freq'  => 'session',
        ])->assertRedirect(route('admin.site.index'));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-rp-popup', $html);
        $this->assertStringContainsString('Eid schedule notice', $html);
        $this->assertStringContainsString('https://example.com/offer', $html);
        $this->assertStringContainsString('"session"', $html);
        $this->assertStringContainsString('5 * 1000', $html);
    }

    public function test_admin_can_use_image_url(): void
    {
        $this->actingAs($this->admin())->post('/admin/site', [
            'popup_on'      => '1',
            'popup_img_url' => 'https://example.com/banner.png',
            'popup_delay'   => '0',
            'popup_freq'    => 'always',
        ])->assertRedirect(route('admin.site.index'));

        $this->get('/')->assertOk()
            ->assertSee('https://example.com/banner.png', false)
            ->assertSee('data-rp-popup', false);
    }

    public function test_admin_can_upload_popup_image(): void
    {
        $this->actingAs($this->admin())->post('/admin/site', [
            'popup_on'   => '1',
            'popup_file' => UploadedFile::fake()->image('ad.png', 400, 300),
            'popup_freq' => 'day',
        ])->assertRedirect(route('admin.site.index'));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#/uploads/popup/popup-[a-z0-9]{16}\.png#', $html);
    }

    public function test_bad_frequency_and_delay_are_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/site', [
            'popup_on'    => '1',
            'popup_freq'  => 'hourly',
            'popup_delay' => '999',
        ])->assertSessionHasErrors(['popup_freq', 'popup_delay']);
    }

    public function test_guest_cannot_save_popup(): void
    {
        $this->post('/admin/site', ['popup_on' => '1', 'popup_text' => 'x'])
            ->assertRedirect('/login');
    }

    public function test_turning_popup_off_removes_markup(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/site', [
            'popup_on' => '1', 'popup_text' => 'Hello', 'popup_freq' => 'day',
        ]);
        $this->actingAs($admin)->post('/admin/site', [
            'popup_freq' => 'day',
        ]);

        $this->get('/')->assertOk()->assertDontSee('data-rp-popup', false);
    }
}
