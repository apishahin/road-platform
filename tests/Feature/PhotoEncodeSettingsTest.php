<?php

namespace Tests\Feature;

use App\Models\Evidence;
use App\Models\User;
use App\Services\EvidenceService;
use App\Support\{PhotoEncodeSettings, StorageSettings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhotoEncodeSettingsTest extends TestCase
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
        PhotoEncodeSettings::save(1600, 'jpeg', 80);
        parent::tearDown();
    }

    public function test_defaults_match_old_behaviour(): void
    {
        $this->assertSame(['max' => 1600, 'format' => 'jpeg', 'quality' => 80], PhotoEncodeSettings::all());
        $this->assertSame('jpg', PhotoEncodeSettings::ext());
    }

    public function test_admin_can_save_and_validation_works(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.storage.encode'), [
            'max' => 1280, 'format' => 'webp', 'quality' => 75,
        ])->assertRedirect();

        $this->assertSame(['max' => 1280, 'format' => 'webp', 'quality' => 75], PhotoEncodeSettings::all());
        $this->assertSame('webp', PhotoEncodeSettings::ext());

        $this->actingAs($admin)->post(route('admin.storage.encode'), [
            'max' => 999, 'format' => 'gif', 'quality' => 10,
        ])->assertSessionHasErrors(['max', 'format', 'quality']);

        $this->actingAs($this->admin())->get(route('admin.storage.index'))
            ->assertOk()->assertSee('data-encode-settings', false);
    }

    public function test_uploads_respect_size_and_format(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('gd not available');
        }
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('webp not available');
        }

        PhotoEncodeSettings::save(1024, 'webp', 75);

        $disk = StorageSettings::privateDisk();
        $ev   = app(EvidenceService::class)->store($this->bigPng(), $this->admin()->id);

        // processed copy is .webp and capped at 1024px
        $this->assertStringEndsWith('.webp', $ev->public_path);
        $info = getimagesizefromstring(Storage::disk($disk)->get($ev->public_path));
        $this->assertNotFalse($info);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertLessThanOrEqual(1024, max($info[0], $info[1]));

        // back to jpeg → .jpg again
        PhotoEncodeSettings::save(1600, 'jpeg', 80);
        $ev2 = app(EvidenceService::class)->store($this->bigPng(), $this->admin()->id);
        $this->assertStringEndsWith('.jpg', $ev2->public_path);

        Storage::disk($disk)->delete(array_filter([$ev->public_path, $ev->private_path, $ev2->public_path, $ev2->private_path]));
    }

    public function test_photo_write_failure_returns_a_safe_storage_message(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('gd not available');
        }

        $disk = 'test_evidence_bad_local';
        $root = storage_path('framework/testing/evidence-file-' . Str::random(12));
        $previousPrivate = config('filesystems.evidence.private');
        $previousDisk = config('filesystems.disks.' . $disk);
        File::put($root, 'This deliberately is a file, not an evidence directory.');
        config([
            'filesystems.evidence.private' => $disk,
            'filesystems.disks.' . $disk => ['driver' => 'local', 'root' => $root, 'throw' => true],
        ]);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(__('report.photo_storage_unavailable'));
            app(EvidenceService::class)->store($this->bigPng(), $this->admin()->id);
        } finally {
            Storage::forgetDisk($disk);
            config([
                'filesystems.evidence.private' => $previousPrivate,
                'filesystems.disks.' . $disk => $previousDisk,
            ]);
            File::delete($root);
        }
    }

    public function test_local_evidence_folder_tree_is_created_before_a_photo_write(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('gd not available');
        }

        $disk = 'test_evidence_local';
        $root = storage_path('framework/testing/evidence-local-' . Str::random(12));
        $previousPrivate = config('filesystems.evidence.private');
        $previousDisk = config('filesystems.disks.' . $disk);
        File::deleteDirectory($root);
        config([
            'filesystems.evidence.private' => $disk,
            'filesystems.disks.' . $disk => ['driver' => 'local', 'root' => $root, 'throw' => true],
        ]);

        try {
            $evidence = app(EvidenceService::class)->store($this->bigPng(), $this->admin()->id);
            $this->assertFileExists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $evidence->public_path));
            $this->assertFileExists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $evidence->private_path));
        } finally {
            Storage::forgetDisk($disk);
            config([
                'filesystems.evidence.private' => $previousPrivate,
                'filesystems.disks.' . $disk => $previousDisk,
            ]);
            File::deleteDirectory($root);
        }
    }
}
