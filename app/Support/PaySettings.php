<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Online donation gateway (UddoktaPay) options, stored as one JSON value
 * in `settings`. Defaults keep online donations OFF until configured.
 */
class PaySettings
{
    public const KEY = 'pay_settings';

    public static function defaults(): array
    {
        return [
            'up_enabled' => false,
            'up_base_url' => '',   // e.g. https://pay.your-domain.com (or sandbox)
            'up_api_key' => '',    // Dashboard -> API settings
            'up_min_amount' => 10, // taka
        ];
    }

    private static function raw(): ?array
    {
        try {
            $v = DB::table('settings')->where('key', self::KEY)->value('value');
        } catch (\Throwable $e) {
            return null;
        }
        if (! $v) {
            return null;
        }
        $a = json_decode($v, true);

        return is_array($a) ? $a : null;
    }

    public static function sanitize(array $in): array
    {
        $out = [];
        if (array_key_exists('up_enabled', $in)) {
            $out['up_enabled'] = filter_var($in['up_enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('up_base_url', $in)) {
            $out['up_base_url'] = rtrim(trim((string) $in['up_base_url']), '/');
        }
        if (array_key_exists('up_api_key', $in)) {
            $out['up_api_key'] = trim((string) $in['up_api_key']);
        }
        if (array_key_exists('up_min_amount', $in)) {
            $out['up_min_amount'] = max(1, min(1000000, (int) $in['up_min_amount']));
        }

        return $out;
    }

    public static function all(): array
    {
        return array_merge(self::defaults(), self::sanitize(self::raw() ?? []));
    }

    public static function save(array $new): void
    {
        $data = array_merge(self::all(), self::sanitize($new));
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($data), 'updated_at' => now()]
        );
    }

    public static function enabled(): bool
    {
        return (bool) self::all()['up_enabled'];
    }

    /** Ready to take payments: enabled AND fully configured. */
    public static function ready(): bool
    {
        $s = self::all();

        return $s['up_enabled'] && $s['up_base_url'] !== '' && $s['up_api_key'] !== '';
    }

    public static function baseUrl(): string
    {
        return (string) self::all()['up_base_url'];
    }

    public static function apiKey(): string
    {
        return (string) self::all()['up_api_key'];
    }

    public static function minAmount(): int
    {
        return (int) self::all()['up_min_amount'];
    }
}
