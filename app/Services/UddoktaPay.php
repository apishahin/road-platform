<?php

namespace App\Services;

use App\Support\PaySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UddoktaPay gateway (self-hosted or sandbox).
 *
 * Create charge: POST {base}/api/checkout-v2      -> payment_url to redirect the donor to
 * Verify:        POST {base}/api/verify-payment   -> real payment state for an invoice
 *
 * Auth: RT-UDDOKTAPAY-API-KEY header. We never trust callback/webhook payloads;
 * every notification is confirmed with a server-side verify call.
 */
class UddoktaPay
{
    /**
     * @return array{ok: bool, payment_url?: string, message?: string}
     */
    public function createCharge(array $payload): array
    {
        if (! PaySettings::ready()) {
            return ['ok' => false, 'message' => 'gateway_not_configured'];
        }

        try {
            $r = Http::acceptJson()
                ->withHeaders(['RT-UDDOKTAPAY-API-KEY' => PaySettings::apiKey()])
                ->timeout(20)
                ->post(PaySettings::baseUrl().'/api/checkout-v2', array_merge([
                    'return_type' => 'GET',
                ], $payload));
        } catch (\Throwable $e) {
            Log::warning('UddoktaPay createCharge error: '.$e->getMessage());

            return ['ok' => false, 'message' => 'gateway_unreachable'];
        }

        $url = (string) ($r->json('payment_url') ?? '');
        if ($r->successful() && $url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => true, 'payment_url' => $url];
        }

        Log::warning('UddoktaPay createCharge failed: HTTP '.$r->status().' '.mb_substr($r->body(), 0, 300));

        return ['ok' => false, 'message' => (string) ($r->json('message') ?? 'gateway_rejected')];
    }

    /**
     * @return array<string, mixed>|null Decoded response (status, invoice_id, amount,
     *                                   transaction_id, payment_method, sender_number, metadata, ...) or null on failure.
     */
    public function verify(string $invoiceId): ?array
    {
        if (! PaySettings::ready() || $invoiceId === '') {
            return null;
        }

        try {
            $r = Http::acceptJson()
                ->withHeaders(['RT-UDDOKTAPAY-API-KEY' => PaySettings::apiKey()])
                ->timeout(15)
                ->post(PaySettings::baseUrl().'/api/verify-payment', ['invoice_id' => $invoiceId]);
        } catch (\Throwable $e) {
            Log::warning('UddoktaPay verify error: '.$e->getMessage());

            return null;
        }

        $data = $r->json();
        if (! $r->successful() || ! is_array($data) || ! isset($data['status'])) {
            Log::warning('UddoktaPay verify failed: HTTP '.$r->status().' for invoice '.$invoiceId);

            return null;
        }
        $data['status'] = strtoupper((string) $data['status']);

        return $data;
    }
}
