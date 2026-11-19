<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature   = 'backup:run {--uploads : include public/uploads images}';
    protected $description = 'Create a full-site backup zip (used by the nightly schedule)';

    public function handle(BackupService $backups): int
    {
        try {
            $name    = $backups->create($this->option('uploads'), 'auto');
            $pruned  = $backups->pruneAuto();
            $this->info("Backup created: {$name}".($pruned ? " ({$pruned} old auto-backup(s) pruned)" : ''));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
