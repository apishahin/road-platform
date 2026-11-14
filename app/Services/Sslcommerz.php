<?php

namespace App\Services;

use App\Support\PaySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSLCommerz hosted checkout.
 * Init: POST {base}/gwprocess/v4/api.php            -> GatewayPageURL to redirect donor
 * Verify: GET {base}/validator/api/validationserverAPI.php?val_id=...  (server-side, always)
 * base = sandbox.sslcommerz.com (test) or securepay.sslcommerz.com (live).
 */
class Sslcommerz
{
    public function baseUrl(): string
    {
        return PaySettings::get('ssl_sandbox', true)
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    /** @return array{ok: bool, gateway_url?: string, message?: string} */
    public function init(array $fields): array
    {
        if (! PaySettings::ready()) {
            return ['ok' => false, 'message' => 'gateway_not_configured'];
        }

        try {
            $r = Http::asForm()->acceptJson()->timeout(25)->post(
                $this->baseUrl() . '/gwprocess/v4/api.php',
                array_merge([
                    'store_id'         => PaySettings::get('ssl_store_id'),
                    'store_passwd'     => PaySettings::get('ssl_store_passwd'),
                    'currency'         => 'BDT',
                    'shipping_method'  => 'NO',
                    'num_of_item'      => 1,
                    'product_name'     => 'Road Platform Donation',
                    'product_category' => 'General',
                    'product_profile'  => 'general',
                ], $fields)
            );
        } catch (\Throwable $e) {
            Log::warning('SSLCommerz init error: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'gateway_unreachable'];
        }

        $j = $r->json();
        $url = is_array($j) ? (string) ($j['GatewayPageURL'] ?? '') : '';
        if ($r->successful() && $url !== '' && strtoupper((string) ($j['status'] ?? '')) === 'SUCCESS' && filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => true, 'gateway_url' => $url];
        }

        Log::warning('SSLCommerz init failed: HTTP ' . $r->status() . ' ' . mb_substr($r->body(), 0, 300));

        return ['ok' => false, 'message' => is_array($j) ? (string) ($j['failedreason'] ?? 'gateway_rejected') : 'gateway_rejected'];
    }

    /**
     * Confirm with SSLCommerz what really happened for a val_id.
     * @return array{valid: bool, tran_id: string, amount: int, bank_tran_id: string, card_type: string}|null
     */
    public function validatePayment(string $valId): ?array
    {
        if (! PaySettings::ready() || $valId === '') {
            return null;
        }

        try {
            $r = Http::acceptJson()->timeout(15)->get($this->baseUrl() . '/validator/api/validationserverAPI.php', [
                'val_id'       => $valId,
                'store_id'     => PaySettings::get('ssl_store_id'),
                'store_passwd' => PaySettings::get('ssl_store_passwd'),
                'format'       => 'json',
            ]);
        } catch (\Throwable $e) {
            Log::warning('SSLCommerz validate error: ' . $e->getMessage());

            return null;
        }

        $j = $r->json();
        if (! $r->successful() || ! is_array($j) || ! isset($j['status'])) {
            Log::warning('SSLCommerz validate failed: HTTP ' . $r->status());

            return null;
        }

        return [
            'valid'        => in_array(strtoupper((string) $j['status']), ['VALID', 'VALIDATED'], true),
            'tran_id'      => (string) ($j['tran_id'] ?? ''),
            'amount'       => (int) round((float) ($j['currency_amount'] ?? $j['amount'] ?? 0)),
            'bank_tran_id' => (string) ($j['bank_tran_id'] ?? ''),
            'card_type'    => (string) ($j['card_type'] ?? ''),
        ];
    }
}
