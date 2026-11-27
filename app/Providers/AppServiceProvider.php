<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Some SQLite builds (including the Windows PHP/XAMPP distribution)
        // omit optional trigonometric SQL functions. Register small PHP-backed
        // equivalents for the location-distance query used in tests; MySQL and
        // production SQLite builds continue to use their native functions.
        if (config('database.default') === 'sqlite') {
            try {
                $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
                if (method_exists($pdo, 'sqliteCreateFunction')) {
                    $pdo->sqliteCreateFunction('RADIANS', static fn (float $degrees): float => deg2rad($degrees), 1);
                    $pdo->sqliteCreateFunction('SIN', static fn (float $value): float => sin($value), 1);
                    $pdo->sqliteCreateFunction('COS', static fn (float $value): float => cos($value), 1);
                    $pdo->sqliteCreateFunction('ACOS', static fn (float $value): float => acos($value), 1);
                }
            } catch (\Throwable) {
                // Database boot must remain available even if PDO extensions
                // are limited; production deployments use MySQL's native SQL.
            }
        }

        \App\Support\MailSettings::apply();
        // Database-backed R2 settings override legacy .env values per request.
        \App\Support\StorageSettings::apply();
        \App\Support\PlatformHooks::register();
        //
    }
}
