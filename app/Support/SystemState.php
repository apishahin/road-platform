<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tiny system-wide flags (soft maintenance mode) stored in the settings
 * table under the key "system". No static memoization: the row is tiny and
 * this is read once per request at most.
 */
class SystemState
{
    public const KEY = 'system';

    /** @return array{on: bool, message: string} */
    public static function maintenance(): array
    {
        try {
            $raw  = DB::table('settings')->where('key', self::KEY)->value('value');
            $data = $raw ? (json_decode($raw, true) ?: []) : [];
        } catch (\Throwable) {
            $data = []; // settings table missing (early boot) — never break the site
        }

        return [
            'on'      => (bool) ($data['maintenance_on'] ?? false),
            'message' => (string) ($data['maintenance_message'] ?? ''),
        ];
    }

    public static function save(bool $on, ?string $message): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode([
                'maintenance_on'      => $on,
                'maintenance_message' => (string) $message,
            ]), 'updated_at' => now()]
        );
    }
}
