<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Weekly admin digest email. Settings key "digest".
 *   { on: bool, emails: string[] }
 */
class DigestSettings
{
    public const KEY = 'digest';

    public static function on(): bool
    {
        return (bool) static::all()['on'];
    }

    public static function emails(): array
    {
        return static::all()['emails'];
    }

    public static function all(): array
    {
        $raw = DB::table('settings')->where('key', self::KEY)->value('value');
        $d   = $raw ? (json_decode((string) $raw, true) ?: []) : [];

        $emails = [];
        foreach ((array) ($d['emails'] ?? []) as $e) {
            $e = mb_substr(trim((string) $e), 0, 190);
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $e;
            }
        }

        return ['on' => (bool) ($d['on'] ?? false), 'emails' => array_slice($emails, 0, 20)];
    }

    /** @param string $csv comma/newline separated addresses */
    public static function save(bool $on, string $csv): void
    {
        $emails = preg_split('/[\s,;]+/', $csv) ?: [];

        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode(['on' => $on, 'emails' => $emails], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
    }
}
