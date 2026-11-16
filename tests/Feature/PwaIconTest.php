<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PwaSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class PwaIconTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'role'      => 'citizen',
            'phone_verified_at' => now(),
        ], $over)));
    }

    private function png(): UploadedFile
    {
        // 300×300 green PNG produced in-memory (no fixtures on disk).
        $im = imagecreatetruecolor(300, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, 5, 150, 105));
        $path = tempnam(sys_get_temp_dir(), 'ic') . '.png';
        imagepng($im, $path);

        return new UploadedFile($path, 'icon.png', 'image/png', null, true);
    }

    protected function tearDown(): void
    {
        PwaSettings::dropFiles(PwaSettings::icon());
        parent::tearDown();
    }

    public function test_guests_and_citizens_cannot_change_the_icon(): void
    {
        // Guests bounce to login (patch-23 dashboard is the 403 exception).
        $this->post(route('admin.appearance.pwa.icon'))->assertRedirect(route('login'));
        $this->actingAs($this->user(['phone' => '+8801700000031']))
            ->post(route('admin.appearance.pwa.icon'))->assertForbidden();
    }

    public function test_admin_uploads_icon_and_manifest_uses_it(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $admin = $this->user(['phone' => '+8801700000099', 'role' => 'admin']);
        $this->actingAs($admin)
            ->post(route('admin.appearance.pwa.icon'), ['icon' => $this->png()])
            ->assertRedirect();

        $icon = PwaSettings::icon();
        $this->assertNotNull($icon);

        foreach ([512, 192, 180] as $px) {
            $p = public_path("uploads/pwa/icon-$px-{$icon['file']}.png");
            $this->assertFileExists($p);
            $this->assertSame([$px, $px], array_slice(getimagesize($p), 0, 2));
        }

        $this->get('/manifest.json')
            ->assertOk()
            ->assertJsonPath('icons.0.src', '/uploads/pwa/icon-192-' . $icon['file'] . '.png');
    }

    public function test_removing_the_icon_restores_the_bundled_one(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $admin = $this->user(['phone' => '+8801700000099', 'role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.appearance.pwa.icon'), ['icon' => $this->png()]);
        $this->assertNotNull(PwaSettings::icon());

        $this->actingAs($admin)->post(route('admin.appearance.pwa.icon.remove'))
            ->assertRedirect();
        $this->assertNull(PwaSettings::icon());

        $this->get('/manifest.json')
            ->assertJsonPath('icons.0.src', '/icons/icon-192.png');
    }

    public function test_manifest_prefers_bn_site_name(): void
    {
        app()->setLocale('bn');
        \DB::table('settings')->updateOrInsert(
            ['key' => 'appearance'],
            ['value' => json_encode(['name_bn' => 'আমার রাস্তা'])]
        );
        \App\Support\AppearanceSettings::reset(); // clears the in-memory cache
        // keep the name
        \DB::table('settings')->updateOrInsert(
            ['key' => 'appearance'],
            ['value' => json_encode(['name_bn' => 'আমার রাস্তা'])]
        );

        $this->get('/manifest.json')
            ->assertOk()
            ->assertJsonPath('name', 'আমার রাস্তা');
    }

    public function test_install_prompt_markup_is_on_the_home_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="pwa-helper"', false)
            ->assertSee('beforeinstallprompt', false)
            ->assertSee('data-pwa-install', false);
    }
}
