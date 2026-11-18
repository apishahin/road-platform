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

    public const SECTIONS   = ['hero', 'stats', 'fund', 'ribbon', 'map', 'how', 'repaired', 'cta', 'privacy'];
    public const SIZES      = ['normal', 'large', 'xlarge'];
    public const NAV_ITEMS  = ['home', 'issues', 'dashboard', 'report', 'lang', 'fund'];
    public const PAGES      = ['home', 'report'];
    public const DESIGNS    = ['classic', 'modern'];

    public const HERO_SLIDES = 3;              // max slides
    public const HERO_DIR    = 'uploads/hero'; // inside public/
    public const SOCIALS     = ['facebook', 'youtube', 'x', 'website'];

    /** Admin-editable Modern-home colour/gradient theme (hex only). */
    public const THEME_COLORS = ['green', 'green_dark', 'green_deep', 'accent', 'cta_from', 'cta_to'];

    /** Floating button ids, sizes (sm/md/lg) and positions (bl/bc/br). */
    public const FLOAT_BTNS  = ['pill', 'fab', 'top'];
    public const FLOAT_SIZES = ['sm', 'md', 'lg'];
    public const FLOAT_POS   = ['bl', 'bc', 'br'];

    /** Ad-popup re-show frequency options + upload dir (inside public/). */
    public const POPUP_FREQS = ['always', 'session', 'day'];
    public const POPUP_DIR   = 'uploads/popup';

    /** text fields editable per section */
    public const TEXT_FIELDS = [
        'hero'     => ['title', 'text', 'cta_label', 'cta_sub'],
        'stats'    => [],
        'fund'     => ['note'],
        'ribbon'   => ['l1', 'l2', 'l3', 'l4', 'district_n'],
        'map'      => ['title'],
        'how'      => ['s1t', 's1x', 's2t', 's2x', 's3t', 's3x', 's4t', 's4x'],
        'repaired' => ['title', 'text'],
        'cta'      => ['title', 'text', 'btn', 'url'],
        'privacy'  => ['text'],
    ];

    /** per-section ON/OFF sub-toggles (metrics inside the section) */
    public const BOOL_FIELDS = [
        'fund'   => ['m_donors', 'm_projects', 'm_days'],
        'ribbon' => ['m1', 'm2', 'm3', 'm4', 'show_trend', 'show_districts', 'show_categories'],
    ];

    /** per-section choice fields: field => allowed values (first = default) */
    public const SELECT_FIELDS = [
        'ribbon' => [
            'trend_style' => ['vbars', 'hbars', 'line'],
            'dist_style'  => ['bars', 'donut'],
            'cat_style'   => ['bars', 'donut', 'pie'],
        ],
    ];

    /** per-section colour fields (hex only) */
    public const COLOR_FIELDS = [
        'ribbon' => ['color1', 'color2'],
    ];

    /** sections that have a heading size control */
    public const SIZE_FIELDS = ['hero', 'map', 'how', 'repaired', 'cta'];

    public static function defaults(): array
    {
        return [
            'sections' => [
                'hero'     => ['on' => true,  'size' => 'normal', 'title' => '', 'text' => '', 'cta_label' => '', 'cta_sub' => ''],
                'stats'    => ['on' => true],
                'fund'     => ['on' => true,  'note' => '', 'm_donors' => true, 'm_projects' => true, 'm_days' => true],
                'ribbon'   => ['on' => false, 'l1' => '', 'l2' => '', 'l3' => '', 'l4' => '', 'district_n' => '', 'm1' => true, 'm2' => true, 'm3' => true, 'm4' => true, 'show_trend' => true, 'show_districts' => true, 'show_categories' => true, 'trend_style' => 'vbars', 'dist_style' => 'bars', 'cat_style' => 'bars', 'color1' => '#22c55e', 'color2' => '#0ea5e9'],
                'map'      => ['on' => true,  'size' => 'normal', 'title' => ''],
                'how'      => ['on' => true,  'size' => 'normal', 's1t' => '', 's1x' => '', 's2t' => '', 's2x' => '', 's3t' => '', 's3x' => '', 's4t' => '', 's4x' => ''],
                'repaired' => ['on' => true,  'size' => 'normal', 'title' => '', 'text' => ''],
                'cta'      => ['on' => false, 'size' => 'normal', 'title' => '', 'text' => '', 'btn' => '', 'url' => ''],
                'privacy'  => ['on' => true,  'text' => ''],
            ],
            'nav'    => ['home' => true, 'issues' => true, 'dashboard' => true, 'report' => true, 'lang' => true, 'fund' => false],
            'footer' => ['text' => '', 'links_on' => true, 'dark' => false, 'facebook' => '', 'youtube' => '', 'x' => '', 'website' => ''],
            'design' => ['home' => 'classic', 'report' => 'classic'],
            'hero_slider' => ['on' => false, 'slides' => [self::emptySlide(), self::emptySlide(), self::emptySlide()]],
            'theme' => [
                'on'         => false,
                'green'      => '#078a48', 'green_dark' => '#00552f', 'green_deep' => '#003d25',
                'accent'     => '#d7ff70', 'cta_from'   => '#006b39', 'cta_to'     => '#003d25',
            ],
            // Floating buttons (install pill / report FAB / scroll-to-top):
            // on/off, label, colour, size, screen position — all admin-controlled.
            'float' => [
                'pill_on' => true,  'pill_label' => '', 'pill_color' => '#059669', 'pill_size' => 'md', 'pill_pos' => 'bc', 'pill_bottom' => 16, 'pill_side' => 16,
                'fab_on'  => true,  'fab_label'  => '', 'fab_color'  => '#059669', 'fab_size'  => 'md', 'fab_pos'  => 'br', 'fab_bottom'  => 16, 'fab_side'  => 16,
                'top_on'  => true,  'top_color'  => '#334155', 'top_size' => 'md', 'top_pos' => 'bl', 'top_bottom' => 16, 'top_side' => 16,
            ],
            // Advertisement popup: text and/or image (upload or URL), clickable
            // link, show-after delay and re-show frequency — all admin-controlled.
            'popup' => ['on' => false, 'text' => '', 'link' => '', 'img' => '', 'img_url' => '', 'delay' => 3, 'freq' => 'day'],
        ];
    }

    private static function emptySlide(): array
    {
        return ['img' => '', 'url' => '', 'title' => '', 'text' => '', 'tag' => ''];
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

    /** Only allow safe URLs: site-relative, or http(s). */
    public static function cleanUrl($v, int $max = 300): string
    {
        $v = mb_substr(trim((string) $v), 0, $max);
        if ($v === '') {
            return '';
        }
        if (str_starts_with($v, '/') && ! str_starts_with($v, '//')) {
            return $v;
        }
        if (preg_match('#^https?://#i', $v)) {
            return $v;
        }

        return '';
    }

    /** Sanitize one hero slide row. Uploaded image = generated safe filename only. */
    private static function cleanSlide(array $in): array
    {
        $img = (string) ($in['img'] ?? '');
        if (! preg_match('/^hero-[a-z0-9]{16}\.(png|jpg|webp)$/', $img)) {
            $img = '';
        }

        return [
            'img'   => $img,
            'url'   => self::cleanUrl($in['url'] ?? ''),
            'title' => mb_substr(trim((string) ($in['title'] ?? '')), 0, 300),
            'text'  => mb_substr(trim((string) ($in['text'] ?? '')), 0, 500),
            'tag'   => mb_substr(trim((string) ($in['tag'] ?? '')), 0, 100),
        ];
    }

    public static function sanitize(array $in): array
    {
        $d = self::defaults();
        $secIn = is_array($in['sections'] ?? null) ? $in['sections'] : [];
        $navIn = is_array($in['nav'] ?? null) ? $in['nav'] : [];
        $ftrIn = is_array($in['footer'] ?? null) ? $in['footer'] : [];

        $out = ['sections' => [], 'nav' => [], 'footer' => [], 'design' => [], 'hero_slider' => [], 'theme' => [], 'float' => [], 'popup' => []];

        foreach (self::SECTIONS as $s) {
            $base = $d['sections'][$s];
            $over = is_array($secIn[$s] ?? null) ? $secIn[$s] : [];
            $row = ['on' => array_key_exists('on', $over) ? (bool) $over['on'] : $base['on']];
            if (in_array($s, self::SIZE_FIELDS, true)) {
                $row['size'] = in_array($over['size'] ?? '', self::SIZES, true) ? $over['size'] : $base['size'];
            }
            foreach (self::TEXT_FIELDS[$s] as $f) {
                $v = $over[$f] ?? $base[$f];
                $row[$f] = $f === 'url' ? self::cleanUrl($v) : mb_substr(trim((string) $v), 0, 500);
            }
            foreach (self::BOOL_FIELDS[$s] ?? [] as $b) {
                $row[$b] = array_key_exists($b, $over) ? (bool) $over[$b] : $base[$b];
            }
            foreach (self::SELECT_FIELDS[$s] ?? [] as $f => $allowed) {
                $v       = $over[$f] ?? $base[$f];
                $row[$f] = in_array($v, $allowed, true) ? $v : $base[$f];
            }
            foreach (self::COLOR_FIELDS[$s] ?? [] as $f) {
                $v       = (string) ($over[$f] ?? $base[$f]);
                $row[$f] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $base[$f];
            }
            $out['sections'][$s] = $row;
        }

        foreach (self::NAV_ITEMS as $n) {
            $out['nav'][$n] = array_key_exists($n, $navIn) ? (bool) $navIn[$n] : $d['nav'][$n];
        }

        $out['footer'] = [
            'text'     => mb_substr(trim((string) ($ftrIn['text'] ?? $d['footer']['text'])), 0, 1000),
            'links_on' => array_key_exists('links_on', $ftrIn) ? (bool) $ftrIn['links_on'] : $d['footer']['links_on'],
            'dark'     => array_key_exists('dark', $ftrIn) ? (bool) $ftrIn['dark'] : $d['footer']['dark'],
        ];
        foreach (self::SOCIALS as $soc) {
            $out['footer'][$soc] = self::cleanUrl($ftrIn[$soc] ?? '');
        }

        $dsnIn = is_array($in['design'] ?? null) ? $in['design'] : [];
        foreach (self::PAGES as $pg) {
            $v = $dsnIn[$pg] ?? $d['design'][$pg];
            $out['design'][$pg] = in_array($v, self::DESIGNS, true) ? $v : $d['design'][$pg];
        }

        $hsIn = is_array($in['hero_slider'] ?? null) ? $in['hero_slider'] : [];
        $slidesIn = is_array($hsIn['slides'] ?? null) ? $hsIn['slides'] : [];
        $slides = [];
        for ($i = 0; $i < self::HERO_SLIDES; $i++) {
            $slides[] = self::cleanSlide(is_array($slidesIn[$i] ?? null) ? $slidesIn[$i] : []);
        }
        $out['hero_slider'] = [
            'on'     => (bool) ($hsIn['on'] ?? $d['hero_slider']['on']),
            'slides' => $slides,
        ];

        $thIn = is_array($in['theme'] ?? null) ? $in['theme'] : [];
        $out['theme'] = ['on' => (bool) ($thIn['on'] ?? $d['theme']['on'])];
        foreach (self::THEME_COLORS as $c) {
            $v = $thIn[$c] ?? $d['theme'][$c];
            $out['theme'][$c] = (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? strtolower($v) : $d['theme'][$c];
        }

        $flIn = is_array($in['float'] ?? null) ? $in['float'] : [];
        $fl = [];
        foreach (self::FLOAT_BTNS as $b) {
            $fl[$b . '_on'] = array_key_exists($b . '_on', $flIn) ? (bool) $flIn[$b . '_on'] : $d['float'][$b . '_on'];
            if ($b !== 'top') {
                $fl[$b . '_label'] = mb_substr(trim((string) ($flIn[$b . '_label'] ?? $d['float'][$b . '_label'])), 0, 60);
            }
            $c = (string) ($flIn[$b . '_color'] ?? $d['float'][$b . '_color']);
            $fl[$b . '_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $d['float'][$b . '_color'];
            $sz = $flIn[$b . '_size'] ?? '';
            $fl[$b . '_size'] = in_array($sz, self::FLOAT_SIZES, true) ? $sz : $d['float'][$b . '_size'];
            $ps = $flIn[$b . '_pos'] ?? '';
            $fl[$b . '_pos'] = in_array($ps, self::FLOAT_POS, true) ? $ps : $d['float'][$b . '_pos'];
            foreach (['bottom', 'side'] as $off) {
                $v = $flIn[$b . '_' . $off] ?? $d['float'][$b . '_' . $off];
                $fl[$b . '_' . $off] = is_numeric($v) ? max(0, min(400, (int) $v)) : $d['float'][$b . '_' . $off];
            }
        }
        $out['float'] = $fl;

        $ppIn = is_array($in['popup'] ?? null) ? $in['popup'] : [];
        $ppImg = (string) ($ppIn['img'] ?? '');
        if (! preg_match('/^popup-[a-z0-9]{16}\.(png|jpg|webp)$/', $ppImg)) {
            $ppImg = '';
        }
        $ppDelay = $ppIn['delay'] ?? $d['popup']['delay'];
        $out['popup'] = [
            'on'      => array_key_exists('on', $ppIn) ? (bool) $ppIn['on'] : $d['popup']['on'],
            'text'    => mb_substr(trim((string) ($ppIn['text'] ?? $d['popup']['text'])), 0, 300),
            'link'    => self::cleanUrl($ppIn['link'] ?? $d['popup']['link']),
            'img'     => $ppImg,
            'img_url' => self::cleanUrl($ppIn['img_url'] ?? $d['popup']['img_url']),
            'delay'   => is_numeric($ppDelay) ? max(0, min(120, (int) $ppDelay)) : $d['popup']['delay'],
            'freq'    => in_array($ppIn['freq'] ?? '', self::POPUP_FREQS, true) ? $ppIn['freq'] : $d['popup']['freq'],
        ];

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

    /** Per-section metric/sub-toggle ON/OFF. Unknown = on. */
    public static function boolOn(string $section, string $key): bool
    {
        return (bool) (self::all()['sections'][$section][$key] ?? true);
    }

    /** Choice field value (e.g. chart style). */
    public static function sel(string $section, string $field): string
    {
        return (string) (self::all()['sections'][$section][$field] ?? '');
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
    public static function txt(string $section, string $field, string $fallback = ''): string
    {
        $v = trim((string) (self::all()['sections'][$section][$field] ?? ''));

        return $v !== '' ? $v : $fallback;
    }

    /** Hero slider config: ['on'=>bool, 'slides'=>[...]]; slides with no image/url are dropped for display. */
    public static function heroSlider(): array
    {
        $hs = self::all()['hero_slider'];
        $slides = [];
        foreach ($hs['slides'] as $sl) {
            if ($sl['img'] !== '' || $sl['url'] !== '') {
                $slides[] = $sl;
            }
        }
        $hs['slides'] = $slides;

        return $hs;
    }

    /** Public URL of an uploaded hero slide image. */
    public static function heroImgUrl(string $file): string
    {
        return asset(self::HERO_DIR . '/' . $file);
    }

    /** Default social-share image: first hero slide, else the PWA icon. */
    public static function ogImage(): string
    {
        foreach (self::all()['sections']['hero_slider']['slides'] ?? [] as $s) {
            if (($s['img'] ?? '') !== '') {
                return self::heroImgUrl($s['img']);
            }
            if (($s['url'] ?? '') !== '') {
                return $s['url'];
            }
        }

        return asset('icons/icon-512.png');
    }

    /** Theme config; 'on' = admin overrides active, else spec defaults still returned. */
    public static function theme(): array
    {
        return self::all()['theme'];
    }

    /** Render slide text with *asterisked* words wrapped in highlight spans. */
    public static function heroHl(string $t): string
    {
        if (mb_substr_count($t, '*') < 2) {
            return e($t);
        }
        $out = '';
        foreach (explode('*', $t) as $i => $part) {
            $out .= $i % 2 === 1 ? '<span class="hl">' . e($part) . '</span>' : e($part);
        }

        return $out;
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
