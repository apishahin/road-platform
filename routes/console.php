<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly full-site backup (toggleable from Admin → System). Needs one cron
// entry on the server: * * * * * cd <project> && php artisan schedule:run
\Illuminate\Support\Facades\Schedule::command('backup:run')
    ->dailyAt('03:00')
    ->when(fn () => \App\Support\SystemState::autoBackupOn());
