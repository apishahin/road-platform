<?php

namespace App\Services;

use App\Models\Evidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use RuntimeException;

class EvidenceService
{
    /**
     * Store an uploaded photo:
     * 1. Save the original privately (for verification only)
     * 2. Create a public copy: resized, compressed, EXIF stripped
     * 3. Return an Evidence model (moderation starts as "pending")
     *
     * EXIF data (GPS, camera info) is stripped from the public copy
     * so the uploaded file never leaks the reporter's exact location
     * beyond what they chose to put on the map.
     */
    public function store(
        UploadedFile $file,
        int          $uploaderId,
        ?int         $reportId      = null,
        ?int         $statusEventId = null,
    ): Evidence {
        if (! $file->isValid()) {
            throw new RuntimeException('Upload failed.');
        }

        if ($file->getSize() > 20 * 1024 * 1024) {
            throw new RuntimeException('Photo must be under 20 MB.');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw new RuntimeException('Only JPEG, PNG, or WebP images are allowed.');
        }

        $originalName = $file->hashName();           // random safe filename

        // 1. Save original privately
        $privatePath = $file->storeAs(
            'evidence/originals', $originalName, 'private'
        );

        // 2. Create public copy: 1600px max, quality 80, no EXIF
        $manager = new ImageManager(new GdDriver());

        $image = $manager->read($file->getRealPath());

        // Strip all EXIF metadata by re-encoding
        // Extract GPS before stripping (for internal verification use)
        $gps = $this->extractGps($file->getRealPath());

        // Resize: max 1600px on the longest side
        if ($image->width() > 1600 || $image->height() > 1600) {
            $image->scaleDown(maxWidth: 1600, maxHeight: 1600);
        }

        // Encode as JPEG quality 80 (EXIF is dropped automatically)
        $encoded = $image->toJpeg(quality: 80);

        // Also support PNG → JPEG conversion for smaller files
        $publicPath = 'evidence/public/' . pathinfo($originalName, PATHINFO_FILENAME) . '.jpg';

        Storage::disk('public')->put(
            $publicPath,
            $encoded,
            ['visibility' => 'public']
        );

        // 3. Create the Evidence record
        return Evidence::create([
            'report_id'       => $reportId,
            'status_event_id' => $statusEventId,
            'uploader_id'     => $uploaderId,
            'public_path'     => $publicPath,
            'private_path'    => $privatePath,
            'taken_at'        => $this->extractTakenAt($file->getRealPath()) ?? now(),
            'moderation'      => 'pending',
        ]);
    }

    /**
     * Extract GPS coordinates from EXIF for internal verification.
     * These are NOT published on the public copy.
     */
    private function extractGps(string $path): ?array
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($path);
        if (! $exif || ! isset($exif['GPSLatitude'], $exif['GPSLongitude'])) {
            return null;
        }

        // Convert EXIF coordinate format to decimal degrees
        $lat = $this->exifToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
        $lng = $this->exifToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');

        if ($lat === null || $lng === null) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    private function exifToDecimal(array $coords, string $ref): ?float
    {
        try {
            $d = $coords[0];
            $m = $coords[1];
            $s = $coords[2];

            $decimal = $d + ($m / 60) + ($s / 3600);

            if (in_array(strtoupper($ref), ['S', 'W'], true)) {
                $decimal *= -1;
            }

            return round($decimal, 7);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractTakenAt(string $path): ?\DateTime
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