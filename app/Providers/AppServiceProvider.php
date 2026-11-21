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
        \App\Support\MailSettings::apply();
        // Database-backed R2 settings override legacy .env values per request.
        \App\Support\StorageSettings::apply();
        \App\Support\PlatformHooks::register();
        //
    }
}
