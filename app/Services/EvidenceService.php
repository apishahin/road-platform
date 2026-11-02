<?php

namespace App\Services;

use App\Models\Evidence;
use Illuminate\Http\UploadedFile;
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
     * 1. Original -> private disk (verification only, never public)
     * 2. Processed copy (max 1600px, JPEG, no EXIF) -> public disk
     * 3. Evidence row with moderation = pending
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

        try {
            $image = (new ImageManager(new GdDriver()))->read($file->getRealPath());
            if ($image->width() > 1600 || $image->height() > 1600) {
                $image->scaleDown(width: 1600, height: 1600);
            }
            $encoded = (string) $image->toJpeg(quality: 80); // re-encoding drops EXIF
        } catch (\Throwable $e) {
            throw new RuntimeException('Image could not be processed.');
        }

        $privateDisk = config('filesystems.evidence.private');
        $publicDisk  = config('filesystems.evidence.public');
        $base        = (string) Str::uuid();

        $privatePath = 'evidence/originals/' . $base . '.' . self::EXT[$mime];
        $publicPath  = 'evidence/public/' . $base . '.jpg';

        Storage::disk($privateDisk)->put($privatePath, file_get_contents($file->getRealPath()));
        Storage::disk($publicDisk)->put($publicPath, $encoded);

        try {
            return Evidence::create([
                'report_id'       => $reportId,
                'status_event_id' => $statusEventId,
                'uploader_id'     => $uploaderId,
                'public_path'     => $publicPath,
                'private_path'    => $privatePath,
                'taken_at'        => $this->takenAt($file->getRealPath()) ?? now(),
                'moderation'      => 'pending',
            ]);
        } catch (\Throwable $e) {
            Storage::disk($privateDisk)->delete($privatePath);
            Storage::disk($publicDisk)->delete($publicPath);
            throw $e;
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