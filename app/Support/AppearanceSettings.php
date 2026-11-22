<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Site appearance (font, size, colours, name, logo) stored as one JSON value
 * in the settings table under the key "appearance".
 *
 * Every value is re-validated each time it is read, so a bad value in the
 * database can never put unsafe text into the page's CSS or HTML.
 */
class AppearanceSettings
{
    public const KEY   = 'appearance';
    public const DIR   = 'uploads/appearance'; // inside public/
    public const FONTS = ['system', 'noto_google', 'hind_google', 'custom'];
    public const SIZES = ['normal' => '100%', 'large' => '112.5%', 'xlarge' => '125%'];
    public const MAP_DISPLAYS = ['bangladesh', 'world'];

    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'font'        => 'noto_google',
            'font_file'   => null,
            'size'        => 'normal',
            'brand'       => '#ea580c',
            'header_bg'   => '#ffffff',
            'header_text' => '#475569',
            'name_bn'     => '',
            'name_en'     => '',
            'tagline_bn'  => '',
            'tagline_en'  => '',
            'logo'        => null,
            'issue_view'  => 'simple',
            // This controls visual browsing only. Report/API locations remain
            // Bangladesh-only in both display modes.
            'map_display' => 'bangladesh',
        ];
    }

    /** Drop the per-request memo (used by tests and after saving). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Current settings (defaults + saved values, all validated). */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $stored = $decoded;
            }
        } catch (\Throwable $e) {
            // Never break the site because of settings: use defaults.
        }

        return self::$cache = self::sanitize($stored);
    }

    public static function sanitize(array $in): array
    {
        $d   = self::defaults();
        $out = $d;

        $font = is_string($in['font'] ?? null) ? $in['font'] : '';
        if (in_array($font, self::FONTS, true)) {
            $out['font'] = $font;
        }

        $size = is_string($in['size'] ?? null) ? $in['size'] : '';
        if (isset(self::SIZES[$size])) {
            $out['size'] = $size;
        }

        foreach (['brand', 'header_bg', 'header_text'] as $k) {
            $v = $in[$k] ?? null;
            $out[$k] = (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? strtolower($v) : $d[$k];
        }

        foreach (['name_bn', 'name_en'] as $k) {
            $v = is_string($in[$k] ?? null) ? $in[$k] : '';
            $out[$k] = mb_substr(trim(strip_tags($v)), 0, 60);
        }

        foreach (['tagline_bn', 'tagline_en'] as $k) {
            $v = is_string($in[$k] ?? null) ? $in[$k] : '';
            $out[$k] = mb_substr(trim(strip_tags($v)), 0, 120);
        }

        $ff = $in['font_file'] ?? null;
        if (is_string($ff) && preg_match('/^font-[a-z0-9]{16}\.(woff2|woff|ttf|otf)$/', $ff)) {
            $out['font_file'] = $ff;
        }

        $lg = $in['logo'] ?? null;
        if (is_string($lg) && preg_match('/^logo-[a-z0-9]{16}\.(png|jpg|webp)$/', $lg)) {
            $out['logo'] = $lg;
        }

        $iv = $in['issue_view'] ?? null;
        $out['issue_view'] = in_array($iv, ['simple', 'detailed'], true) ? $iv : 'simple';

        $mapDisplay = $in['map_display'] ?? null;
        $out['map_display'] = in_array($mapDisplay, self::MAP_DISPLAYS, true) ? $mapDisplay : 'bangladesh';

        // A custom font needs an uploaded file
        if ($out['font'] === 'custom' && $out['font_file'] === null) {
            $out['font'] = 'system';
        }

        return $out;
    }

    public static function save(array $values): array
    {
        $clean = self::sanitize($values);

        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
        self::$cache = null;

        return $clean;
    }

    /** Back to defaults: removes saved settings and uploaded files. */
    public static function reset(): void
    {
        $s = self::all();
        self::removeFile($s['font_file']);
        self::removeFile($s['logo']);

        DB::table('settings')->where('key', self::KEY)->delete();
        self::$cache = null;
    }

    /** Delete one uploaded file, only if the name looks exactly like one we created. */
    public static function removeFile(?string $name): void
    {
        if ($name === null || ! preg_match('/^(font|logo)-[a-z0-9]{16}\.[a-z0-9]{3,4}$/', $name)) {
            return;
        }
        $path = public_path(self::DIR . '/' . $name);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // ── Values used on every page ───────────────────────────────

    public static function siteName(): string
    {
        $s = self::all();
        $name = app()->getLocale() === 'bn' ? $s['name_bn'] : $s['name_en'];

        return $name !== '' ? $name : __('Road Platform');
    }

    public static function logoUrl(): ?string
    {
        $logo = self::all()['logo'];

        return $logo ? asset(self::DIR . '/' . $logo) : null;
    }

    /** Visual browsing policy shared by every in-application Leaflet map. */
    public static function mapDisplay(): string
    {
        return self::all()['map_display'];
    }

    /** Short line shown under the site name (header + footer). Empty = hidden. */
    public static function tagline(): string
    {
        $s = self::all();

        return app()->getLocale() === 'bn' ? $s['tagline_bn'] : $s['tagline_en'];
    }

    /**
     * Local stylesheet that self-hosts the chosen webfont (no CDN needed —
     * works offline, on localhost, and where Google Fonts is unreachable).
     * Null for 'system'/'custom' (custom has its @font-face inline in css()).
     */
    public static function fontStylesheet(string $font): ?string
    {
        $file = match ($font) {
            'noto_google'  => 'fonts/noto-sans-bengali.css',
            'hind_google'  => 'fonts/hind-siliguri.css',
            default        => null,
        };
        if ($file === null) {
            return null;
        }
        $path = public_path($file);

        return asset($file) . '?v=' . (is_file($path) ? filemtime($path) : '1');
    }

    private static function fontStack(array $s): string
    {
        $tail = "'Nirmala UI','Bangla Sangam MN','Kohinoor Bangla','Segoe UI',system-ui,ui-sans-serif,sans-serif";

        return match ($s['font']) {
            'hind_google'  => "'Hind Siliguri'," . $tail,
            'custom'       => "'RPCustom'," . $tail,
            'noto_google'  => "'Noto Sans Bengali'," . $tail,
            default        => $tail, // system: the visitor's own OS Bengali font
        };
    }

    /** CSS placed in every page's <head>. Contains only validated values. */
    public static function css(): string
    {
        $s = self::all();
        $d = self::defaults();
        $css = '';

        if ($s['font'] === 'custom' && $s['font_file']) {
            $ext = pathinfo($s['font_file'], PATHINFO_EXTENSION);
            $fmt = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'][$ext] ?? 'truetype';
            $url = asset(self::DIR . '/' . $s['font_file']);
            $css .= "@font-face{font-family:'RPCustom';src:url('{$url}') format('{$fmt}');font-weight:100 900;font-display:swap;}";
        }

        $vars = '--font-sans:' . self::fontStack($s) . ';';
        if ($s['brand'] !== $d['brand']) {
            $b = $s['brand'];
            $vars .= '--color-brand-50:'  . self::mix($b, '#ffffff', 0.92) . ';';
            $vars .= '--color-brand-100:' . self::mix($b, '#ffffff', 0.84) . ';';
            $vars .= '--color-brand-500:' . self::mix($b, '#ffffff', 0.12) . ';';
            $vars .= '--color-brand-600:' . $b . ';';
            $vars .= '--color-brand-700:' . self::mix($b, '#000000', 0.17) . ';';
        }
        $css .= ':root{' . $vars . '}';
        $css .= 'body{font-family:var(--font-sans);}';

        if ($s['size'] !== 'normal') {
            $css .= 'html{font-size:' . self::SIZES[$s['size']] . ';}';
        }
        if ($s['header_bg'] !== $d['header_bg']) {
            $css .= '.rp-header{background-color:' . $s['header_bg'] . ';}';
        }
        if ($s['header_text'] !== $d['header_text']) {
            $css .= '.rp-header a:not([class*="bg-brand"]),.rp-header button{color:' . $s['header_text'] . ';}';
        }

        return $css;
    }

    // ── Colour helpers ──────────────────────────────────────────

    /** Mix $hex with $other. $ratio is how much of $other (0 to 1). */
    public static function mix(string $hex, string $other, float $ratio): string
    {
        $a = self::rgb($hex);
        $b = self::rgb($other);
        $out = '#';
        foreach (range(0, 2) as $i) {
            $out .= str_pad(dechex((int) round($a[$i] * (1 - $ratio) + $b[$i] * $ratio)), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /** WCAG contrast ratio (1 to 21). */
    public static function contrast(string $hexA, string $hexB): float
    {
        $la = self::luminance($hexA);
        $lb = self::luminance($hexB);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luminance(string $hex): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }
}