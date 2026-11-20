<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Facebook auto-share (page post when an issue is marked repaired).
 * Settings key "social_share". Default OFF. Token stored encrypted.
 */
class ShareSettings
{
    public const KEY = 'social_share';

    public static function on(): bool
    {
        return (bool) static::all()['on'];
    }

    public static function all(): array
    {
        $raw = DB::table('settings')->where('key', self::KEY)->value('value');
        $d   = $raw ? (json_decode((string) $raw, true) ?: []) : [];

        return [
            'on'         => (bool) ($d['on'] ?? false),
            'fb_page_id' => (string) ($d['fb_page_id'] ?? ''),
            'fb_token'   => (string) ($d['fb_token'] ?? ''), // encrypted
        ];
    }

    public static function token(): string
    {
        $enc = static::all()['fb_token'];
        if ($enc === '') {
            return '';
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function save(array $in): void
    {
        $cur  = static::all();
        $tok  = trim((string) ($in['fb_token'] ?? ''));
        $enc  = $tok !== '' ? Crypt::encryptString($tok) : $cur['fb_token'];

        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode([
                'on'         => ! empty($in['on']),
                'fb_page_id' => mb_substr(trim((string) ($in['fb_page_id'] ?? '')), 0, 100),
                'fb_token'   => $enc,
            ], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
    }
}
