<?php

namespace App\Console\Commands;

use App\Models\Evidence;
use App\Support\StorageSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Re-compresses stored original uploads in place (max 1600px, JPEG q80).
 * Safe to run repeatedly; skips small files. Use --dry-run to preview.
 */
class ShrinkPhotos extends Command
{
    protected $signature = 'photos:shrink {--dry-run : Only count what would be saved}';
    protected $description = 'Re-compress stored evidence originals to save storage space.';

    private const SKIP_BYTES = 700 * 1024; // leave files ≤700KB alone

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $manager = new ImageManager(new GdDriver());

        $scanned = 0;
        $done = 0;
        $saved = 0;

        Evidence::whereNotNull('private_path')
            ->where('private_path', 'like', 'evidence/originals/%')
            ->select(['id', 'private_path', 'private_disk'])
            ->chunkById(200, function ($rows) use ($dry, $manager, &$scanned, &$done, &$saved) {
                foreach ($rows as $row) {
                    $diskName = $row->private_disk ?: StorageSettings::privateDisk();
                    $disk = Storage::disk($diskName);
                    $path = $row->private_path;

                    try {
                        if (! $disk->exists($path)) {
                            continue;
                        }
                        $size = $disk->size($path);
                        if ($size <= self::SKIP_BYTES) {
                            continue;
                        }
                        $scanned++;

                        if ($dry) {
                            $done++;
                            continue;
                        }

                        $image = method_exists($manager, 'decode')
                            ? $manager->decode($disk->get($path))
                            : $manager->read($disk->get($path));
                        if ($image->width() > 1600 || $image->height() > 1600) {
                            $image->orient();
                            $image->scaleDown(width: 1600, height: 1600);
                        }
                        $out = (string) $image->encode(new JpegEncoder(quality: 80));

                        if ($out !== '' && strlen($out) < $size) {
                            $disk->put($path, $out);
                            $saved += $size - strlen($out);
                            $done++;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("Evidence #{$row->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info(($dry ? '[dry-run] ' : '')
            . "Scanned {$scanned} large original(s), re-compressed {$done}, saved "
            . round($saved / 1048576, 1) . ' MB.');

        return self::SUCCESS;
    }
}
