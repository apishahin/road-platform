<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * How uploaded photos are stored after processing (Admin → Storage).
 * Settings key "photo_encode": { max: int, format: 'jpeg'|'webp', quality: int }
 * Defaults match the historical behaviour: 1600px / JPEG / 80.
 */
class PhotoEncodeSettings
{
    public const KEY = 'photo_encode';

    public const SIZES   = [1024, 1280, 1600, 2048];
    public const FORMATS = ['jpeg', 'webp'];
    public const Q_MIN   = 50;
    public const Q_MAX   = 92;

    public static function all(): array
    {
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            $raw = null;
        }
        $d = $raw ? (json_decode((string) $raw, true) ?: []) : [];

        $max = (int) ($d['max'] ?? 1600);
        $fmt = (string) ($d['format'] ?? 'jpeg');

        return [
            'max'     => in_array($max, self::SIZES, true) ? $max : 1600,
            'format'  => in_array($fmt, self::FORMATS, true) ? $fmt : 'jpeg',
            'quality' => max(self::Q_MIN, min(self::Q_MAX, (int) ($d['quality'] ?? 80))),
        ];
    }

    public static function maxDim(): int
    {
        return static::all()['max'];
    }

    public static function format(): string
    {
        return static::all()['format'];
    }

    public static function quality(): int
    {
        return static::all()['quality'];
    }

    /** File extension for the processed copy. */
    public static function ext(): string
    {
        return static::format() === 'webp' ? 'webp' : 'jpg';
    }

    public static function save(int $max, string $format, int $quality): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode([
                'max'     => in_array($max, self::SIZES, true) ? $max : 1600,
                'format'  => in_array($format, self::FORMATS, true) ? $format : 'jpeg',
                'quality' => max(self::Q_MIN, min(self::Q_MAX, $quality)),
            ]), 'updated_at' => now()]
        );
    }
}
