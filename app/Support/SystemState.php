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

    /** @return array<string, mixed> */
    private static function data(): array
    {
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');

            return $raw ? (json_decode($raw, true) ?: []) : [];
        } catch (\Throwable) {
            return []; // settings table missing (early boot) — never break the site
        }
    }

    /** Merge-update the row so unrelated system flags survive. */
    private static function put(array $patch): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode(array_merge(self::data(), $patch)), 'updated_at' => now()]
        );
    }

    /** @return array{on: bool, message: string} */
    public static function maintenance(): array
    {
        $data = self::data();

        return [
            'on'      => (bool) ($data['maintenance_on'] ?? false),
            'message' => (string) ($data['maintenance_message'] ?? ''),
        ];
    }

    public static function save(bool $on, ?string $message): void
    {
        self::put([
            'maintenance_on'      => $on,
            'maintenance_message' => (string) $message,
        ]);
    }

    /** Scheduled nightly backup — on unless an admin turns it off. */
    public static function autoBackupOn(): bool
    {
        return (bool) (self::data()['auto_backup'] ?? true);
    }

    public static function saveAutoBackup(bool $on): void
    {
        self::put(['auto_backup' => $on]);
    }
}
