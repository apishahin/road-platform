<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Web-push (Firebase Cloud Messaging) configuration.
 * Settings key "push". Everything defaults to OFF/empty.
 * Secrets (service-account JSON) are stored encrypted.
 */
class PushSettings
{
    public const KEY = 'push';

    public static function on(): bool
    {
        return (bool) static::all()['on'];
    }

    public static function all(): array
    {
        $raw = DB::table('settings')->where('key', self::KEY)->value('value');
        $d   = $raw ? (json_decode((string) $raw, true) ?: []) : [];

        return [
            'on'        => (bool) ($d['on'] ?? false),
            'api_key'   => (string) ($d['api_key'] ?? ''),
            'project_id' => (string) ($d['project_id'] ?? ''),
            'app_id'    => (string) ($d['app_id'] ?? ''),
            'sender_id' => (string) ($d['sender_id'] ?? ''),
            'vapid_key' => (string) ($d['vapid_key'] ?? ''),
            'sa_json'   => (string) ($d['sa_json'] ?? ''), // encrypted
        ];
    }

    /** Public web config safe to expose to the browser. */
    public static function webConfig(): array
    {
        $s = static::all();

        return [
            'apiKey'            => $s['api_key'],
            'projectId'         => $s['project_id'],
            'appId'             => $s['app_id'],
            'messagingSenderId' => $s['sender_id'],
        ];
    }

    /** Decoded service-account array, or null. */
    public static function serviceAccount(): ?array
    {
        $enc = static::all()['sa_json'];
        if ($enc === '') {
            return null;
        }
        try {
            $json = Crypt::decryptString($enc);
            $sa   = json_decode($json, true);

            return is_array($sa) && isset($sa['client_email'], $sa['private_key'], $sa['project_id']) ? $sa : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function hasServiceAccount(): bool
    {
        return static::serviceAccount() !== null;
    }

    /** Blank secrets keep the old value. */
    public static function save(array $in): void
    {
        $cur = static::all();
        $sa  = trim((string) ($in['sa_json'] ?? ''));
        $saEnc = $cur['sa_json'];
        if ($sa !== '') {
            $decoded = json_decode($sa, true);
            if (is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'])) {
                $saEnc = Crypt::encryptString($sa);
            }
        }

        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode([
                'on'        => ! empty($in['on']),
                'api_key'   => mb_substr(trim((string) ($in['api_key'] ?? '')), 0, 200),
                'project_id' => mb_substr(trim((string) ($in['project_id'] ?? '')), 0, 100),
                'app_id'    => mb_substr(trim((string) ($in['app_id'] ?? '')), 0, 200),
                'sender_id' => mb_substr(trim((string) ($in['sender_id'] ?? '')), 0, 50),
                'vapid_key' => mb_substr(trim((string) ($in['vapid_key'] ?? '')), 0, 200),
                'sa_json'   => $saEnc,
            ], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
    }
}
