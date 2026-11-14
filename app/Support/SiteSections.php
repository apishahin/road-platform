<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Homepage section control + nav/footer switches, stored as one JSON value
 * in the settings table under the key "site".
 *
 * Every value is re-validated on read. Empty text means "use the translated
 * default wording", so admins can toggle/override without re-translating.
 */
class SiteSections
{
    public const KEY = 'site';

    public const SECTIONS   = ['hero', 'stats', 'fund', 'map', 'how', 'repaired', 'privacy'];
    public const SIZES      = ['normal', 'large', 'xlarge'];
    public const NAV_ITEMS  = ['home', 'issues', 'report', 'lang', 'fund'];
    public const PAGES      = ['home', 'report'];
    public const DESIGNS    = ['classic', 'modern'];

    /** text fields editable per section */
    public const TEXT_FIELDS = [
        'hero'     => ['title', 'text'],
        'stats'    => [],
        'fund'     => [],
        'map'      => ['title'],
        'how'      => ['s1t', 's1x', 's2t', 's2x', 's3t', 's3x'],
        'repaired' => ['title', 'text'],
        'privacy'  => ['text'],
    ];

    /** sections that have a heading size control */
    public const SIZE_FIELDS = ['hero', 'map', 'how', 'repaired'];

    public static function defaults(): array
    {
        return [
            'sections' => [
                'hero'     => ['on' => true,  'size' => 'normal', 'title' => '', 'text' => ''],
                'stats'    => ['on' => true],
                'fund'     => ['on' => true],
                'map'      => ['on' => true,  'size' => 'normal', 'title' => ''],
                'how'      => ['on' => true,  'size' => 'normal', 's1t' => '', 's1x' => '', 's2t' => '', 's2x' => '', 's3t' => '', 's3x' => ''],
                'repaired' => ['on' => true,  'size' => 'normal', 'title' => '', 'text' => ''],
                'privacy'  => ['on' => true,  'text' => ''],
            ],
            'nav'    => ['home' => true, 'issues' => true, 'report' => true, 'lang' => true, 'fund' => false],
            'footer' => ['text' => '', 'links_on' => true],
            'design' => ['home' => 'classic', 'report' => 'classic'],
        ];
    }

    public static function all(): array
    {
        // NOTE: no static memoization here — the settings table row is tiny,
        // and a static cache leaks stale values between DB rebuilds (tests)
        // and long-running workers.
        $stored = [];
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $stored = $decoded;
            }
        } catch (\Throwable $e) {
            // never break the site on bad settings
        }

        return self::sanitize($stored);
    }

    public static function sanitize(array $in): array
    {
        $d = self::defaults();
        $secIn = is_array($in['sections'] ?? null) ? $in['sections'] : [];
        $navIn = is_array($in['nav'] ?? null) ? $in['nav'] : [];
        $ftrIn = is_array($in['footer'] ?? null) ? $in['footer'] : [];

        $out = ['sections' => [], 'nav' => [], 'footer' => [], 'design' => []];

        foreach (self::SECTIONS as $s) {
            $base = $d['sections'][$s];
            $over = is_array($secIn[$s] ?? null) ? $secIn[$s] : [];
            $row = ['on' => array_key_exists('on', $over) ? (bool) $over['on'] : $base['on']];
            if (in_array($s, self::SIZE_FIELDS, true)) {
                $row['size'] = in_array($over['size'] ?? '', self::SIZES, true) ? $over['size'] : $base['size'];
            }
            foreach (self::TEXT_FIELDS[$s] as $f) {
                $v = $over[$f] ?? $base[$f];
                $row[$f] = mb_substr(trim((string) $v), 0, 500);
            }
            $out['sections'][$s] = $row;
        }

        foreach (self::NAV_ITEMS as $n) {
            $out['nav'][$n] = array_key_exists($n, $navIn) ? (bool) $navIn[$n] : $d['nav'][$n];
        }

        $out['footer'] = [
            'text'     => mb_substr(trim((string) ($ftrIn['text'] ?? $d['footer']['text'])), 0, 1000),
            'links_on' => array_key_exists('links_on', $ftrIn) ? (bool) $ftrIn['links_on'] : $d['footer']['links_on'],
        ];

        $dsnIn = is_array($in['design'] ?? null) ? $in['design'] : [];
        foreach (self::PAGES as $pg) {
            $v = $dsnIn[$pg] ?? $d['design'][$pg];
            $out['design'][$pg] = in_array($v, self::DESIGNS, true) ? $v : $d['design'][$pg];
        }

        return $out;
    }

    public static function save(array $new): void
    {
        $clean = self::sanitize($new);
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($clean), 'updated_at' => now()]
        );
        Cache::forget('settings.exists'); // in case a warm cache cared
    }

    /** Section ON/OFF. Unknown section = on (safe default). */
    public static function on(string $section): bool
    {
        return (bool) (self::all()['sections'][$section]['on'] ?? true);
    }

    /** Nav item ON/OFF. Unknown item = on. */
    public static function navOn(string $item): bool
    {
        return (bool) (self::all()['nav'][$item] ?? true);
    }

    /** Active design variant for a page: 'classic' (default) or 'modern'. */
    public static function design(string $page): string
    {
        return self::all()['design'][$page] ?? 'classic';
    }

    public static function modern(string $page): bool
    {
        return self::design($page) === 'modern';
    }

    /** Custom text with translated fallback (empty = fallback). */
    public static function txt(string $section, string $field, string $fallback): string
    {
        $v = trim((string) (self::all()['sections'][$section][$field] ?? ''));

        return $v !== '' ? $v : $fallback;
    }

    /** Heading size class for section headings (Tailwind text-* utility). */
    public static function sizeClass(string $section): string
    {
        $size = self::all()['sections'][$section]['size'] ?? 'normal';

        return match ($size) {
            'large'  => 'text-2xl sm:text-3xl',
            'xlarge' => 'text-3xl sm:text-4xl',
            default  => 'text-xl sm:text-2xl',
        };
    }
}
