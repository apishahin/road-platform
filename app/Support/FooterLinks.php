<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Admin-controlled footer links, stored as one JSON value in the settings
 * table under the key "footer_links".
 *
 *   {
 *     builtin: { home: bool, issues: bool, ... , pages: bool },  // default: all ON
 *     custom:  [ { label: string, url: string } ]
 *   }
 *
 * Zero-risk: when nothing is saved yet, the footer looks exactly as before
 * (every built-in link shown, no custom links).
 */
class FooterLinks
{
    public const KEY = 'footer_links';

    /** Built-in links in display order. key => [route-or-url, label-key] */
    public const BUILTIN = [
        'home'        => ['home', 'Home'],
        'issues'      => ['issues.index', 'Issues'],
        'dashboard'   => ['dashboard', 'issues.nav_dashboard'],
        'report'      => ['url:/report', 'Report a problem'],
        'fund'        => ['fund.show', 'issues.site_nav_fund'],
        'impact'      => ['impact', 'issues.impact_nav'],
        'leaderboard' => ['leaderboard', 'issues.lb_nav'],
        'pages'       => ['pages', ''], // all custom pages from Page::menuItems()
    ];

    /** @return array<string, bool> */
    public static function builtin(): array
    {
        $saved = static::data()['builtin'];
        $out = [];
        foreach (array_keys(self::BUILTIN) as $k) {
            $out[$k] = array_key_exists($k, $saved) ? (bool) $saved[$k] : true;
        }

        return $out;
    }

    public static function builtinOn(string $key): bool
    {
        return static::builtin()[$key] ?? true;
    }

    /** @return array<int, array{label:string, url:string}> */
    public static function custom(): array
    {
        return static::data()['custom'];
    }

    /** @param array<string, mixed> $flags */
    public static function saveBuiltin(array $flags): void
    {
        $d = static::data();
        $d['builtin'] = [];
        foreach (array_keys(self::BUILTIN) as $k) {
            $d['builtin'][$k] = ! empty($flags[$k]);
        }
        static::put($d);
    }

    public static function addCustom(string $label, string $url): void
    {
        $d = static::data();
        if (count($d['custom']) >= 20) {
            return;
        }
        $d['custom'][] = ['label' => mb_substr(trim($label), 0, 40), 'url' => static::cleanUrl($url)];
        static::put($d);
    }

    public static function deleteCustom(int $index): void
    {
        $d = static::data();
        if (! isset($d['custom'][$index])) {
            return;
        }
        array_splice($d['custom'], $index, 1);
        static::put($d);
    }

    // ── internals ─────────────────────────────────────

    private static function data(): array
    {
        $raw = DB::table('settings')->where('key', static::KEY)->value('value');
        $d   = $raw ? (json_decode((string) $raw, true) ?: []) : [];

        $custom = [];
        foreach ((array) ($d['custom'] ?? []) as $it) {
            $label = mb_substr(trim((string) ($it['label'] ?? '')), 0, 40);
            $url   = static::cleanUrl($it['url'] ?? null);
            if ($label !== '' && $url !== '') {
                $custom[] = ['label' => $label, 'url' => $url];
            }
        }

        return [
            'builtin' => (array) ($d['builtin'] ?? []),
            'custom'  => $custom,
        ];
    }

    private static function put(array $d): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => static::KEY],
            ['value' => json_encode($d, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
    }

    /** Allow only http(s) URLs or root-relative paths. */
    private static function cleanUrl(?string $url): string
    {
        $url = trim((string) $url);
        if (preg_match('#^https?://#i', $url) || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return mb_substr($url, 0, 300);
        }

        return '';
    }
}
