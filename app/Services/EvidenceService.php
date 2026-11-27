<?php

namespace App\Services;

use App\Models\Evidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use RuntimeException;

class EvidenceService
{
    private const MAX_BYTES  = 20 * 1024 * 1024;
    private const MAX_PIXELS = 40_000_000;
    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * Original and processed copy (max 1600px, JPEG, no EXIF) both go to the
     * PRIVATE disk. ModerationService copies the processed one to the public
     * disk only after a moderator approves it.
     */
    public function store(
        UploadedFile $file,
        int $uploaderId,
        ?int $reportId = null,
        ?int $statusEventId = null,
    ): Evidence {
        if (! $file->isValid()) {
            throw new RuntimeException('Upload failed.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Photo must be under 20 MB.');
        }

        $mime = $file->getMimeType();
        if (! isset(self::EXT[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, or WebP images are allowed.');
        }

        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] < 1 || $size[1] < 1 || ($size[0] * $size[1]) > self::MAX_PIXELS) {
            throw new RuntimeException('Image is invalid or too large.');
        }

        $enc = \App\Support\PhotoEncodeSettings::all();

        try {
            $manager = new ImageManager(new GdDriver()); $image = method_exists($manager, 'decode') ? $manager->decode($file->getRealPath()) : $manager->read($file->getRealPath());
            if ($image->width() > $enc['max'] || $image->height() > $enc['max']) {
                $image->orient(); $image->scaleDown(width: $enc['max'], height: $enc['max']);
            }
            $encoder = $enc['format'] === 'webp'
                ? new \Intervention\Image\Encoders\WebpEncoder(quality: $enc['quality'])
                : new \Intervention\Image\Encoders\JpegEncoder(quality: $enc['quality']);
            $encoded = (string) $image->encode($encoder); // re-encoding drops EXIF
        } catch (\Throwable $e) {
            report($e); throw new RuntimeException('Image could not be processed.');
        }

        $disk        = \App\Support\StorageSettings::privateDisk();
        $base        = (string) Str::uuid();
        $privatePath = 'evidence/originals/' . $base . '.' . self::EXT[$mime];
        $pendingPath = 'evidence/pending/' . $base . '.' . \App\Support\PhotoEncodeSettings::ext();
        $keepOrig    = \App\Support\StorageSettings::keepOriginals();

        // Explicitly create the local folder tree. Some Windows/XAMPP PHP
        // installations cannot create Flysystem's nested directory lazily,
        // which used to surface an internal path as a citizen-facing error.
        try {
            if ($keepOrig) {
                $this->write($disk, $privatePath, (string) file_get_contents($file->getRealPath()));
            }
            $this->write($disk, $pendingPath, $encoded);
        } catch (\Throwable $e) {
            $this->cleanup($disk, [$keepOrig ? $privatePath : null, $pendingPath]);
            throw $e;
        }

        try {
            return Evidence::create([
                'report_id'       => $reportId,
                'status_event_id' => $statusEventId,
                'uploader_id'     => $uploaderId,
                'public_path'     => $pendingPath, // lives on the private disk until approved
                'private_path'    => $keepOrig ? $privatePath : null,
                'taken_at'        => $this->takenAt($file->getRealPath()) ?? now(),
                'moderation'      => 'pending',
            ]);
        } catch (\Throwable $e) {
            $this->cleanup($disk, [$keepOrig ? $privatePath : null, $pendingPath]);
            throw $e;
        }
    }

    /** Best-effort cleanup must never expose or replace a safe write error. */
    private function cleanup(string $disk, array $paths): void
    {
        try {
            Storage::disk($disk)->delete(array_filter($paths));
        } catch (\Throwable) {
            logger()->warning('Evidence photo cleanup failed', ['disk' => $disk]);
        }
    }

    /**
     * Create nested local folders ourselves before writing. Cloud object stores
     * do not need directories, so their normal Flysystem write is unchanged.
     */
    private function write(string $disk, string $path, string $contents): void
    {
        try {
            $config = (array) config("filesystems.disks.$disk", []);
            if (($config['driver'] ?? null) === 'local') {
                $root = (string) ($config['root'] ?? '');
                if ($root === '') {
                    throw new RuntimeException('Local evidence storage has no root.');
                }
                File::ensureDirectoryExists($root);
                $directory = dirname($path);
                if ($directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
                    File::ensureDirectoryExists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory));
                }
                if (! is_writable($root)) {
                    throw new RuntimeException('Local evidence storage is not writable.');
                }
            }

            if (Storage::disk($disk)->put($path, $contents) !== true) {
                throw new RuntimeException('Evidence write was not accepted.');
            }
        } catch (\Throwable $e) {
            // Keep diagnostics server-side and never show storage paths or a
            // cloud endpoint to a citizen.
            logger()->warning('Evidence photo write failed', ['disk' => $disk, 'path' => $path]);
            throw new RuntimeException(__('report.photo_storage_unavailable'));
        }
    }

    private function takenAt(string $path): ?\DateTime
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($path);
        $date = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;
        if (! $date) {
            return null;
        }

        try {
            return new \DateTime($date);
        } catch (\Throwable) {
            return null;
        }
    }
}