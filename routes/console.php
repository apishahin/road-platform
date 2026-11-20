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

// Scheduler heartbeat — lets the System page verify that cron is actually running.
\Illuminate\Support\Facades\Schedule::call(function () {
    cache()->forever('scheduler_heartbeat', now()->toIso8601String());
})->everyMinute();

// Weekly admin digest email (Mondays 08:00, when enabled in Admin → Integrations)
\Illuminate\Support\Facades\Schedule::command('digest:weekly')
    ->weeklyOn(1, '08:00')
    ->when(fn () => \App\Support\DigestSettings::on());
