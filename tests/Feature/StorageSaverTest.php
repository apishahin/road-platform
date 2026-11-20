<?php

namespace Tests\Feature;

use App\Models\Evidence;
use App\Models\User;
use App\Services\EvidenceService;
use App\Support\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageSaverTest extends TestCase
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

    private function bigPng(): UploadedFile
    {
        $im  = imagecreatetruecolor(2400, 1600);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        imagepng($im, $tmp);
        imagedestroy($im);

        return new UploadedFile($tmp, 'big.png', 'image/png', null, true);
    }

    protected function tearDown(): void
    {
        StorageSettings::setKeepOriginals(true);
        parent::tearDown();
    }

    public function test_original_kept_by_default_and_skipped_when_off(): void
    {
        $disk = StorageSettings::privateDisk();
        $svc  = app(EvidenceService::class);
        $u    = $this->admin();

        // default: original kept
        $ev1 = $svc->store($this->bigPng(), $u->id);
        $this->assertNotNull($ev1->private_path);
        $this->assertTrue(Storage::disk($disk)->exists($ev1->private_path));
        $this->assertTrue(Storage::disk($disk)->exists($ev1->public_path));

        // off: no original, processed copy still stored
        StorageSettings::setKeepOriginals(false);
        $ev2 = $svc->store($this->bigPng(), $u->id);
        $this->assertNull($ev2->private_path);
        $this->assertTrue(Storage::disk($disk)->exists($ev2->public_path));

        // cleanup
        Storage::disk($disk)->delete([$ev1->private_path, $ev1->public_path, $ev2->public_path]);
    }

    public function test_toggle_via_admin_page(): void
    {
        $this->actingAs($this->admin())->post(route('admin.storage.keep-originals'), ['keep' => '0'])
            ->assertRedirect();
        $this->assertFalse(StorageSettings::keepOriginals());

        $this->actingAs($this->admin())->post(route('admin.storage.keep-originals'), ['keep' => '1'])
            ->assertRedirect();
        $this->assertTrue(StorageSettings::keepOriginals());
    }

    public function test_shrink_command_recompresses_big_originals(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('gd not available');
        }

        $disk = StorageSettings::privateDisk();
        $path = 'evidence/originals/shrink-test-' . Str::random(8) . '.png';

        // make a genuinely large original
        $im = imagecreatetruecolor(3000, 2000);
        ob_start();
        imagepng($im, null, 0);
        $bytes = ob_get_clean();
        imagedestroy($im);
        Storage::disk($disk)->put($path, $bytes);
        $before = Storage::disk($disk)->size($path);
        $this->assertGreaterThan(700 * 1024, $before);

        $u = $this->admin();
        Evidence::unguarded(fn () => Evidence::create([
            'uploader_id' => $u->id, 'private_path' => $path, 'public_path' => 'evidence/pending/x.jpg',
            'taken_at' => now(), 'moderation' => 'pending', 'private_disk' => $disk,
        ]));

        // dry run: no change
        $this->artisan('photos:shrink', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame($before, Storage::disk($disk)->size($path));

        // real run: smaller + still a valid image
        $this->artisan('photos:shrink')->assertSuccessful();
        $after = Storage::disk($disk)->size($path);
        $this->assertLessThan($before, $after);
        $info = getimagesizefromstring(Storage::disk($disk)->get($path));
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(1600, max($info[0], $info[1]));

        Storage::disk($disk)->delete($path);
    }
}
