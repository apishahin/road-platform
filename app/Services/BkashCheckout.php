<?php

namespace App\Services;

use App\Models\FundDonation;
use App\Support\PaySettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * bKash tokenized checkout (hosted page).
 * 1) grant token  2) create payment -> bkashURL redirect  3) execute on return.
 * Sandbox: tokenized.sandbox.bka.sh / live: tokenized.pay.bka.sh
 * Every return is confirmed by executing the payment server-side + amount check.
 */
class BkashCheckout
{
    private function base(): string
    {
        $host = PaySettings::get('bk_sandbox', true) ? 'https://tokenized.sandbox.bka.sh' : 'https://tokenized.pay.bka.sh';

        return $host . '/v1.2.0-beta/tokenized/checkout';
    }

    private function client(string $token)
    {
        return Http::acceptJson()->timeout(20)->withHeaders([
            'Authorization' => $token,
            'X-APP-Key'     => PaySettings::get('bk_app_key'),
        ]);
    }

    private function token(): ?string
    {
        if ($t = Cache::get('bkash_id_token')) {
            return $t;
        }

        try {
            $r = Http::acceptJson()->timeout(15)
                ->withHeaders([
                    'username' => PaySettings::get('bk_username'),
                    'password' => PaySettings::get('bk_password'),
                ])
                ->post($this->base() . '/token/grant', [
                    'app_key'    => PaySettings::get('bk_app_key'),
                    'app_secret' => PaySettings::get('bk_app_secret'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('bKash token error: ' . $e->getMessage());

            return null;
        }

        $j = $r->json();
        $id = is_array($j) ? (string) ($j['id_token'] ?? '') : '';
        if ($r->successful() && $id !== '') {
            Cache::put('bkash_id_token', $id, 3000); // tokens last ~1h; refresh early

            return $id;
        }

        Log::warning('bKash token failed: HTTP ' . $r->status());

        return null;
    }

    /** @return array{ok: bool, payment_id?: string, bkash_url?: string, message?: string} */
    public function create(FundDonation $d, string $callbackUrl): array
    {
        if (! PaySettings::ready()) {
            return ['ok' => false, 'message' => 'gateway_not_configured'];
        }
        $token = $this->token();
        if (! $token) {
            return ['ok' => false, 'message' => 'gateway_unreachable'];
        }

        try {
            $r = $this->client($token)->post($this->base() . '/create', [
                'mode'                  => '0011',
                'payerReference'        => $d->donor_contact ?: $d->tran_id,
                'callbackURL'           => $callbackUrl,
                'amount'                => (string) $d->amount,
                'currency'              => 'BDT',
                'intent'                => 'sale',
                'merchantInvoiceNumber' => $d->tran_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('bKash create error: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'gateway_unreachable'];
        }

        $j = $r->json();
        $pid = is_array($j) ? (string) ($j['paymentID'] ?? '') : '';
        $url = is_array($j) ? (string) ($j['bkashURL'] ?? '') : '';
        $code = is_array($j) ? (string) ($j['statusCode'] ?? '') : '';
        if ($pid !== '' && $url !== '' && filter_var($url, FILTER_VALIDATE_URL) && ($code === '0000' || $code === '')) {
            return ['ok' => true, 'payment_id' => $pid, 'bkash_url' => $url];
        }

        Log::warning('bKash create failed: HTTP ' . $r->status() . ' ' . mb_substr($r->body(), 0, 300));

        return ['ok' => false, 'message' => is_array($j) ? (string) ($j['statusMessage'] ?? 'gateway_rejected') : 'gateway_rejected'];
    }

    /**
     * Execute after donor returns. @return array{ok: bool, trx_id: string, amount: int, invoice: string}|null
     */
    public function execute(string $paymentId): ?array
    {
        $token = $this->token();
        if (! $token || $paymentId === '') {
            return null;
        }

        try {
            $r = $this->client($token)->post($this->base() . '/execute', ['paymentID' => $paymentId]);
        } catch (\Throwable $e) {
            Log::warning('bKash execute error: ' . $e->getMessage());

            return null;
        }

        $j = $r->json();
        if (! is_array($j) || ! isset($j['statusCode'])) {
            return null;
        }

        return [
            'ok'      => ($j['statusCode'] === '0000') && (($j['transactionStatus'] ?? '') === 'Completed'),
            'trx_id'  => (string) ($j['trxID'] ?? ''),
            'amount'  => (int) round((float) ($j['amount'] ?? 0)),
            'invoice' => (string) ($j['merchantInvoiceNumber'] ?? ''),
        ];
    }
}
