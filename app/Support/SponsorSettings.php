<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Homepage "Partners / Sponsors" section, stored as one JSON value in the
 * settings table under the key "sponsors". Default: OFF with no items.
 *
 *   { on: bool, title: string, items: [ {name, url, logo} ] }
 *
 * Logos are uploaded into public/uploads/sponsors (same pattern as hero
 * slides) and referenced as a root-relative path.
 */
class SponsorSettings
{
    public const KEY = 'sponsors';
    public const DIR = 'uploads/sponsors';

    public static function on(): bool
    {
        return (bool) static::data()['on'];
    }

    public static function title(): string
    {
        return static::data()['title'];
    }

    /** @return array<int, array{name:string, url:string, logo:string}> */
    public static function items(): array
    {
        return static::data()['items'];
    }

    /** Validated view data for the homepage section. */
    public static function section(): array
    {
        $d = static::data();

        return ['on' => $d['on'], 'title' => $d['title'], 'items' => $d['items']];
    }

    public static function saveSection(bool $on, ?string $title): void
    {
        $d = static::data();
        $d['on']    = $on;
        $d['title'] = mb_substr(trim((string) $title), 0, 80);
        static::put($d);
    }

    public static function add(string $name, ?string $url, ?string $logoPath): void
    {
        $d = static::data();
        if (count($d['items']) >= 24) {
            return;
        }
        $d['items'][] = [
            'name' => mb_substr(trim($name), 0, 80),
            'url'  => static::cleanUrl($url),
            'logo' => static::cleanLogo($logoPath),
        ];
        static::put($d);
    }

    public static function delete(int $index): ?string
    {
        $d = static::data();
        if (! isset($d['items'][$index])) {
            return null;
        }
        $logo = $d['items'][$index]['logo'];
        array_splice($d['items'], $index, 1);
        static::put($d);

        return $logo;
    }

    // ── internals ─────────────────────────────────────

    private static function data(): array
    {
        $raw = DB::table('settings')->where('key', static::KEY)->value('value');
        $d   = $raw ? (json_decode((string) $raw, true) ?: []) : [];

        $items = [];
        foreach ((array) ($d['items'] ?? []) as $it) {
            $name = mb_substr(trim((string) ($it['name'] ?? '')), 0, 80);
            if ($name === '') {
                continue;
            }
            $items[] = [
                'name' => $name,
                'url'  => static::cleanUrl($it['url'] ?? null),
                'logo' => static::cleanLogo($it['logo'] ?? null),
            ];
        }

        return [
            'on'    => (bool) ($d['on'] ?? false),
            'title' => mb_substr(trim((string) ($d['title'] ?? '')), 0, 80),
            'items' => $items,
        ];
    }

    private static function put(array $d): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => static::KEY],
            ['value' => json_encode($d, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
    }

    private static function cleanUrl(?string $url): string
    {
        $url = trim((string) $url);

        return preg_match('#^https?://#i', $url) ? mb_substr($url, 0, 300) : '';
    }

    private static function cleanLogo(?string $logo): string
    {
        $logo = trim((string) $logo);
        // only allow paths inside our upload dir — never external URLs
        return str_starts_with($logo, '/' . static::DIR . '/') ? $logo : '';
    }
}
