<?php

use Illuminate\Support\Facades\Route;
use App\Support\AppearanceSettings;
use App\Support\PwaSettings;

/*
 * Dynamic web-app manifest. Served from PHP so the Bangla site name and a
 * custom admin-uploaded icon end up on the user's home screen unchanged.
 */
Route::get('/manifest.json', function () {
    $icon = PwaSettings::icon();
    $src = function (int $px, bool $maskable = false) use ($icon) {
        $file = $icon
            ? '/uploads/pwa/icon-' . $px . '-' . $icon['file'] . '.png'
            : '/icons/icon' . ($maskable ? '-maskable' : '') . '-' . $px . '.png';

        return [
            'src' => $file,
            'sizes' => $px . 'x' . $px,
            'type' => 'image/png',
            ...($maskable ? ['purpose' => 'maskable'] : []),
        ];
    };

    return response()->json([
        'name'             => AppearanceSettings::siteName(),
        'short_name'       => AppearanceSettings::siteName(),
        'description'      => __('A community-powered road repair platform for Bangladesh.'),
        'lang'             => 'bn',
        'dir'              => 'ltr',
        'start_url'        => '/',
        'scope'            => '/',
        'display'          => 'standalone',
        'orientation'      => 'portrait',
        'theme_color'      => '#059669',
        'background_color' => '#0d2f28',
        'categories'       => ['navigation', 'utilities'],
        'icons'            => [$src(192), $src(512), $src(192, true), $src(512, true)],
    ], 200, ['Cache-Control' => 'public, max-age=3600']);
})->name('pwa.manifest');
