<?php

namespace App\Console\Commands;

use App\Support\StorageSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MoveUnapprovedEvidence extends Command
{
    protected $signature = 'evidence:move-unapproved {target=r2 : local or r2}';

    protected $description = 'Move pending/hidden evidence photos to the private disk of the chosen storage';

    public function handle(): int
    {
        $target = (string) $this->argument('target');

        if (! isset(StorageSettings::OPTIONS[$target])) {
            $this->error('target must be local or r2');
            return self::FAILURE;
        }

        $to = StorageSettings::OPTIONS[$target]['private'];
        $check = StorageSettings::check($to);

        if (! $check['ok']) {
            $this->error("Disk $to is not working: " . $check['message']);
            return self::FAILURE;
        }

        $rows = DB::table('evidence')
            ->where('moderation', '!=', 'approved')
            ->where(function ($q) use ($to) {
                $q->whereNull('private_disk')->orWhere('private_disk', '!=', $to);
            })
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to move.');
            return self::SUCCESS;
        }

        $moved = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $from = $row->private_disk ?: StorageSettings::privateDisk();
            $paths = array_values(array_unique(array_filter([$row->private_path, $row->public_path])));
            $ok = true;

            foreach ($paths as $path) {
                if (! Storage::disk($from)->exists($path)) {
                    $this->warn("Evidence #{$row->id}: file missing on $from: $path");
                    $ok = false;
                    break;
                }

                Storage::disk($to)->put($path, Storage::disk($from)->get($path));

                if (! Storage::disk($to)->exists($path)) {
                    $this->warn("Evidence #{$row->id}: copy to $to failed: $path");
                    $ok = false;
                    break;
                }
            }

            if (! $ok) {
                $skipped++;
                continue;
            }

            DB::table('evidence')->where('id', $row->id)->update(['private_disk' => $to]);

            foreach ($paths as $path) {
                Storage::disk($from)->delete($path);
            }

            $this->info("Evidence #{$row->id}: moved $from -> $to");
            $moved++;
        }

        $this->line("Moved: $moved, skipped: $skipped");

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }
}