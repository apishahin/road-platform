<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageCheck extends Command
{
    protected $signature = 'storage:check';

    protected $description = 'Test the evidence storage disks and PHP image settings';

    public function handle(): int
    {
        $ok = true;

        $disks = [
            'private (originals)' => config('filesystems.evidence.private'),
            'public (processed)'  => config('filesystems.evidence.public'),
        ];

        foreach ($disks as $label => $disk) {
            $this->line("{$label}: disk \"{$disk}\"");
            try {
                $path = 'healthcheck/' . Str::random(12) . '.txt';
                Storage::disk($disk)->put($path, 'ok');
                $read = Storage::disk($disk)->get($path);
                Storage::disk($disk)->delete($path);
                if ($read !== 'ok') {
                    throw new \RuntimeException('Read-back did not match.');
                }
                $this->info('  write/read/delete OK');
            } catch (\Throwable $e) {
                $ok = false;
                $this->error('  FAILED: ' . $e->getMessage());
            }
        }

        if ($disks['private (originals)'] === $disks['public (processed)']) {
            $this->warn('Private and public use the SAME disk. Originals could become public. Fix this before launch.');
        }

        foreach (['gd', 'exif', 'fileinfo'] as $ext) {
            if (extension_loaded($ext)) {
                $this->info("PHP extension {$ext}: loaded");
            } else {
                $ok = false;
                $this->error("PHP extension {$ext}: MISSING (enable it in php.ini)");
            }
        }

        $this->line('upload_max_filesize = ' . ini_get('upload_max_filesize'));
        $this->line('post_max_size       = ' . ini_get('post_max_size'));
        $this->line('max_file_uploads    = ' . ini_get('max_file_uploads'));

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}