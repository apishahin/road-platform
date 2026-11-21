<?php

namespace App\Http\Controllers;

use App\Support\PwaSettings;
use App\Support\QrCodeSvg;
use Illuminate\Http\Request;

class PwaInstallController extends Controller
{
    /** Public landing page for a shareable PWA install link and QR code. */
    public function show(Request $request)
    {
        return view('install', [
            'installUrl' => url('/install'),
        ]);
    }

    /** A same-origin QR code, so sharing an install link never depends on a third party. */
    public function qr(Request $request)
    {
        $svg = QrCodeSvg::svg(url('/install'), __('pwa.install_qr_alt'));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /** Which icon file every device's iOS/Android tiles should use, or null. */
    public static function iconUrl(): ?string
    {
        $icon = PwaSettings::icon();

        return $icon ? '/uploads/pwa/icon-180-' . $icon['file'] . '.png' : null;
    }
}
