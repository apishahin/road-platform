<?php

namespace App\Http\Controllers;

use App\Support\PwaSettings;
use Illuminate\Http\Request;

class PwaInstallController extends Controller
{
    /** Which icon file every device's iOS/Android tiles should use, or null. */
    public static function iconUrl(): ?string
    {
        $icon = PwaSettings::icon();

        return $icon ? '/uploads/pwa/icon-180-' . $icon['file'] . '.png' : null;
    }
}
