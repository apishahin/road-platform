<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public read-only API access (Admin → API অ্যাক্সেস).
 *
 * Settings key "api_access": {
 *   public_read: bool,                    // keyless read allowed (rate-limited)
 *   keys: [ {id, label, prefix, hash, created_at, last_used_at, requests} ]
 * }
 *
 * Only the sha256 hash of a key is stored; the plaintext is shown once at
 * creation time and never recoverable afterwards.
 */
class ApiKeySettings
{
    public const KEY = 'api_access';

    public const RATE_PUBLIC = 30;   // requests/min without a key
    public const RATE_KEYED  = 120;  // requests/min with a valid key

    public static function all(): array
    {
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            $raw = null;
        }
        $d = $raw ? (json_decode((string) $raw, true) ?: []) : [];

        $keys = [];
        foreach ((array) ($d['keys'] ?? []) as $k) {
            if (! isset($k['id'], $k['hash'])) {
                continue;
            }
            $keys[] = [
                'id'          => (string) $k['id'],
                'label'       => (string) ($k['label'] ?? ''),
                'prefix'      => (string) ($k['prefix'] ?? ''),
                'hash'        => (string) $k['hash'],
                'created_at'  => (string) ($k['created_at'] ?? ''),
                'last_used_at' => $k['last_used_at'] ?? null,
                'requests'    => (int) ($k['requests'] ?? 0),
            ];
        }

        return [
            'public_read' => (bool) ($d['public_read'] ?? true),
            'keys'        => $keys,
        ];
    }

    public static function publicRead(): bool
    {
        return static::all()['public_read'];
    }

    public static function savePublicRead(bool $on): void
    {
        $cfg = static::all();
        $cfg['public_read'] = $on;
        static::write($cfg);
    }

    /** Create a new key. Returns [row, plaintext] — plaintext shown only once. */
    public static function createKey(string $label): array
    {
        $plain = 'rp_' . Str::random(40);
        $row = [
            'id'          => (string) Str::ulid(),
            'label'       => mb_substr(trim($label), 0, 80) ?: 'API key',
            'prefix'      => substr($plain, 0, 10),
            'hash'        => hash('sha256', $plain),
            'created_at'  => now()->toDateTimeString(),
            'last_used_at' => null,
            'requests'    => 0,
        ];
        $cfg = static::all();
        $cfg['keys'][] = $row;
        static::write($cfg);

        return [$row, $plain];
    }

    public static function revoke(string $id): void
    {
        $cfg = static::all();
        $cfg['keys'] = array_values(array_filter($cfg['keys'], fn ($k) => $k['id'] !== $id));
        static::write($cfg);
    }

    /** Resolve a plaintext token to its stored row (constant-time compare). */
    public static function findByToken(string $plain): ?array
    {
        $hash = hash('sha256', $plain);
        foreach (static::all()['keys'] as $k) {
            if (hash_equals($k['hash'], $hash)) {
                return $k;
            }
        }

        return null;
    }

    /** Usage accounting — persisted every 25th request to avoid write churn. */
    public static function touch(string $id): void
    {
        $n = (int) cache()->increment('api_usage_' . $id);
        if ($n % 25 !== 1) {
            return;
        }
        $cfg = static::all();
        foreach ($cfg['keys'] as &$k) {
            if ($k['id'] === $id) {
                $k['requests']    = (int) $k['requests'] + 25;
                $k['last_used_at'] = now()->toDateTimeString();
            }
        }
        unset($k);
        static::write($cfg);
    }

    private static function write(array $cfg): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($cfg), 'updated_at' => now()]
        );
    }
}
