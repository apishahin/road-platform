<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Online donation gateways (UddoktaPay / SSLCommerz / bKash), one JSON value
 * in `settings`. Defaults keep online donations OFF until configured.
 * Legacy keys (up_enabled, up_min_amount) auto-migrate to the new shape.
 */
class PaySettings
{
    public const KEY = 'pay_settings';
    public const PROVIDERS = ['none', 'uddoktapay', 'sslcommerz', 'bkash'];

    public static function defaults(): array
    {
        return [
            'provider' => 'none',
            // shared
            'min_amount' => 10, // taka
            // uddoktapay
            'up_base_url' => '',
            'up_api_key' => '',
            // sslcommerz
            'ssl_store_id' => '',
            'ssl_store_passwd' => '',
            'ssl_sandbox' => true,
            // bkash
            'bk_app_key' => '',
            'bk_app_secret' => '',
            'bk_username' => '',
            'bk_password' => '',
            'bk_sandbox' => true,
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

        // Legacy: up_enabled=true meant "uddoktapay on".
        if (! array_key_exists('provider', $in) && array_key_exists('up_enabled', $in)) {
            $in['provider'] = filter_var($in['up_enabled'], FILTER_VALIDATE_BOOLEAN) ? 'uddoktapay' : 'none';
        }
        if (array_key_exists('provider', $in) && in_array($in['provider'], self::PROVIDERS, true)) {
            $out['provider'] = $in['provider'];
        }
        if (array_key_exists('up_min_amount', $in) && ! array_key_exists('min_amount', $in)) {
            $in['min_amount'] = $in['up_min_amount'];
        }
        if (array_key_exists('min_amount', $in)) {
            $out['min_amount'] = max(1, min(1000000, (int) $in['min_amount']));
        }

        foreach (['up_base_url', 'up_api_key', 'ssl_store_id', 'ssl_store_passwd', 'bk_app_key', 'bk_app_secret', 'bk_username', 'bk_password'] as $k) {
            if (array_key_exists($k, $in)) {
                $v = trim((string) $in[$k]);
                $out[$k] = $k === 'up_base_url' ? rtrim($v, '/') : $v;
            }
        }
        foreach (['ssl_sandbox', 'bk_sandbox'] as $k) {
            if (array_key_exists($k, $in)) {
                $out[$k] = filter_var($in[$k], FILTER_VALIDATE_BOOLEAN);
            }
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

    public static function get(string $key, $default = '')
    {
        return self::all()[$key] ?? $default;
    }

    public static function provider(): string
    {
        return (string) self::get('provider', 'none');
    }

    /** Ready to take payments: a provider chosen AND its credentials complete. */
    public static function ready(): bool
    {
        return match (self::provider()) {
            'uddoktapay' => self::get('up_base_url') !== '' && self::get('up_api_key') !== '',
            'sslcommerz' => self::get('ssl_store_id') !== '' && self::get('ssl_store_passwd') !== '',
            'bkash' => self::get('bk_app_key') !== '' && self::get('bk_app_secret') !== ''
                        && self::get('bk_username') !== '' && self::get('bk_password') !== '',
            default => false,
        };
    }

    public static function minAmount(): int
    {
        return max(1, (int) self::get('min_amount', 10));
    }

    /** UddoktaPay API base URL (used by App\\Services\\UddoktaPay). */
    public static function baseUrl(): string
    {
        return rtrim((string) self::get('up_base_url'), '/');
    }

    /** UddoktaPay API key (used by App\\Services\\UddoktaPay). */
    public static function apiKey(): string
    {
        return (string) self::get('up_api_key');
    }

    /** @deprecated legacy call sites — same as ready(). */
    public static function enabled(): bool
    {
        return self::ready();
    }
}
