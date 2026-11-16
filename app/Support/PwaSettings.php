<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PWA icon uploaded from the admin panel (one JSON row under "pwa_icon"
 * in the settings table). The file lives in public/uploads and is
 * stamped square (192 / 512 / 180) with an opaque brand background.
 */
class PwaSettings
{
    public const KEY = 'pwa_icon';
    public const DIR = 'uploads/pwa'; // inside public/

    private static ?array $cache = null;

    /** Current icon info or null when none was uploaded. */
    public static function icon(): ?array
    {
        if (self::$cache !== null) {
            return self::$cache ? self::$cache : null;
        }

        $stored = null;
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && isset($decoded['file'], $decoded['at'])) {
                $stored = $decoded;
            }
        } catch (\Throwable $e) {
            // Bad/missing row: fall back to the bundled icon.
        }

        return (self::$cache = $stored ?: []) ?: null;
    }

    /**
     * Accept an uploaded image, centre-crop it square, and write the
     * 4 PNG sizes the manifest expects. Returns false on any failure.
     * Uses bound GD methods, so it is skipped (not broken) without GD.
     */
    public static function storeIcon(string $tmpPath): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $data = @file_get_contents($tmpPath);
        $src  = $data ? @imagecreatefromstring($data) : false;
        if (! $src) {
            return false;
        }

        $w    = imagesx($src);
        $h    = imagesy($src);
        $side = min($w, $h);
        $crop = imagecrop($src, [
            'x' => (int) (($w - $side) / 2),
            'y' => (int) (($h - $side) / 2),
            'width' => $side, 'height' => $side,
        ]);
        imagedestroy($src);
        if (! $crop) {
            return false;
        }

        // Canvas colour: the icon's own top-centre pixel keeps the shape honest.
        $bg = imagecolorat($crop, (int) ($side / 2), 2);
        $r = ($bg >> 16) & 255; $g = ($bg >> 8) & 255; $b = $bg & 255;

        $dir = public_path(self::DIR);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $token = Str::lower(Str::random(8));
        $out   = ['file' => $token, 'at' => now()->toISOString()];

        foreach ([512 => 1.0, 192 => 1.0, 180 => 1.0] as $px => $scale) {
            $canvas = imagecreatetruecolor($px, $px);
            $fill   = imagecolorallocate($canvas, $r, $g, $b);
            imagefill($canvas, 0, 0, $fill);
            $tw = (int) round($px * $scale);
            imagecopyresampled($canvas, $crop, (int) (($px - $tw) / 2), (int) (($px - $tw) / 2), 0, 0, $tw, $tw, $side, $side);
            if (! imagepng($canvas, $dir . "/icon-$px-$token.png", 6)) {
                imagedestroy($canvas); imagedestroy($crop);
                return false;
            }
            imagedestroy($canvas);
        }
        imagedestroy($crop);

        return true;
    }

    public static function save(?array $icon): void
    {
        if ($icon === null) {
            DB::table('settings')->where('key', self::KEY)->delete();
        } else {
            DB::table('settings')->updateOrInsert(
                ['key' => self::KEY],
                ['value' => json_encode($icon, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
            );
        }
        self::$cache = null;
    }

    /** Delete every stamped size of the current icon (best-effort). */
    public static function dropFiles(?array $icon): void
    {
        $token = $icon['file'] ?? '';
        if (! preg_match('/^[a-z0-9]{8}$/', $token)) {
            return;
        }
        foreach ([512, 192, 180] as $px) {
            $p = public_path(self::DIR . "/icon-$px-$token.png");
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }
}
