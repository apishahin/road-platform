<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Admin-controlled ad slots (Google AdSense or any network's HTML/JS).
 * Everything is OFF and empty by default; stored in settings under "ads".
 *
 * NOTE: slot HTML is rendered raw BY DESIGN — that is how ad tags work.
 * Only admins can edit these settings, same trust level as site settings.
 */
class AdSettings
{
    public const KEY = 'ads';

    /** Named ad positions available across the public pages. */
    public const SLOTS = ['home_after_hero', 'issues_inline', 'issue_bottom', 'dashboard_bottom', 'above_footer'];

    public static function defaults(): array
    {
        return [
            'on'             => false,
            'publisher_id'   => '',   // AdSense ca-pub-… (optional)
            'hide_for_staff' => true, // don't bother admins/moderators with ads
            'ads_txt'        => '',
            'slots'          => array_fill_keys(self::SLOTS, ''),
        ];
    }

    public static function all(): array
    {
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
            $d   = $raw ? (json_decode($raw, true) ?: []) : [];
        } catch (\Throwable) {
            $d = [];
        }

        $out = self::defaults();
        foreach (['on', 'hide_for_staff'] as $b) {
            if (array_key_exists($b, $d)) {
                $out[$b] = (bool) $d[$b];
            }
        }
        foreach (['publisher_id', 'ads_txt'] as $t) {
            if (isset($d[$t]) && is_string($d[$t])) {
                $out[$t] = $d[$t];
            }
        }
        foreach (self::SLOTS as $s) {
            if (isset($d['slots'][$s]) && is_string($d['slots'][$s])) {
                $out['slots'][$s] = $d['slots'][$s];
            }
        }

        return $out;
    }

    public static function save(array $in): void
    {
        $c = self::defaults();
        $c['on']             = ! empty($in['on']);
        $c['hide_for_staff'] = ! empty($in['hide_for_staff']);
        $c['ads_txt']        = mb_substr((string) ($in['ads_txt'] ?? ''), 0, 10000);

        $pub = trim((string) ($in['publisher_id'] ?? ''));
        $c['publisher_id'] = preg_match('/^ca-pub-\d{10,20}$/', $pub) ? $pub : '';

        foreach (self::SLOTS as $s) {
            $c['slots'][$s] = mb_substr((string) (data_get($in, 'slots.'.$s) ?? ''), 0, 8000);
        }

        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($c), 'updated_at' => now()]
        );
    }

    public static function on(): bool
    {
        return self::all()['on'];
    }

    public static function publisherId(): string
    {
        return self::all()['publisher_id'];
    }

    public static function adsTxt(): string
    {
        return self::all()['ads_txt'];
    }

    /** Should ads be visible for the current viewer right now? */
    public static function visible(): bool
    {
        $c = self::all();
        if (! $c['on']) {
            return false;
        }
        if ($c['hide_for_staff']) {
            $u = auth()->user();
            if ($u && ($u->role === 'admin' || (method_exists($u, 'isModerator') && $u->isModerator()))) {
                return false;
            }
        }

        return true;
    }

    /** Raw HTML for a named slot ('' when not visible / empty). */
    public static function slot(string $name): string
    {
        if (! in_array($name, self::SLOTS, true) || ! self::visible()) {
            return '';
        }

        return self::all()['slots'][$name];
    }
}
