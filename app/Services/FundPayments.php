<?php

namespace App\Services;

use App\Models\FundDonation;
use Illuminate\Support\Facades\Log;

/**
 * Settles a donation from a server-verified UddoktaPay invoice.
 * Shared by the browser callback and the webhook/IPN so both stay
 * consistent and idempotent.
 */
class FundPayments
{
    public function __construct(
        private UddoktaPay $gateway,
        private AuditService $audit,
    ) {}

    /**
     * Verify an invoice with the gateway and update our donation row.
     *
     * @return array{result: 'paid'|'pending'|'failed'|'unknown'|'error', donation: ?FundDonation}
     */
    public function settle(string $invoiceId, ?int $expectedDonationId = null, string $source = 'callback'): array
    {
        $v = $this->gateway->verify($invoiceId);
        if ($v === null) {
            return ['result' => 'error', 'donation' => null];
        }

        $donation = $this->findDonation($v, $invoiceId, $expectedDonationId);
        if (! $donation) {
            Log::warning('UddoktaPay: settled invoice maps to no donation', ['invoice' => $invoiceId]);

            return ['result' => 'unknown', 'donation' => null];
        }

        // Always remember the invoice id so admin and retries can match it.
        if ($donation->invoice_id !== $invoiceId) {
            $donation->forceFill(['invoice_id' => $invoiceId])->save();
        }

        $gwStatus = (string) ($v['status'] ?? '');

        if ($gwStatus === 'COMPLETED') {
            $amount = (int) ($v['amount'] ?? 0);
            if ($amount !== (int) $donation->amount) {
                // Amount tampered or gateway fee confusion: keep pending and flag it.
                Log::warning('UddoktaPay: paid amount mismatch', [
                    'invoice' => $invoiceId, 'expected' => $donation->amount, 'got' => $amount,
                ]);

                return ['result' => 'pending', 'donation' => $donation];
            }

            if ($donation->status !== 'paid') {
                $trx = (string) ($v['transaction_id'] ?? '');
                $donation->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'tran_id' => $trx !== '' ? $trx : ($donation->tran_id ?: $invoiceId),
                ])->save();

                $this->audit->log(null, 'fund.paid_online', 'fund_donation', (string) $donation->id, [
                    'invoice' => $invoiceId,
                    'trx' => $trx !== '' ? $trx : null,
                    'method' => $v['payment_method'] ?? null,
                    'amount' => $donation->amount,
                    'source' => $source,
                ]);
            }

            return ['result' => 'paid', 'donation' => $donation];
        }

        if ($gwStatus === 'PENDING') {
            return ['result' => 'pending', 'donation' => $donation];
        }

        // ERROR / CANCELLED / ABORTED / anything else terminal.
        if ($donation->status === 'pending') {
            $donation->forceFill(['status' => 'failed'])->save();
        }

        return ['result' => 'failed', 'donation' => $donation];
    }

    private function findDonation(array $v, string $invoiceId, ?int $expectedDonationId): ?FundDonation
    {
        // 1) We stored the invoice id on an earlier attempt.
        $d = FundDonation::where('gateway', 'uddoktapay')->where('invoice_id', $invoiceId)->first();
        if ($d) {
            return $d;
        }

        // 2) The metadata we passed at checkout time echoes back on verify.
        $meta = $v['metadata'] ?? null;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        $id = is_array($meta) ? (int) ($meta['donation_id'] ?? 0) : 0;
        if ($id > 0) {
            $d = FundDonation::where('gateway', 'uddoktapay')->find($id);
            if ($d) {
                return $d;
            }
        }

        // 3) Browser session fallback (callback flow only).
        if ($expectedDonationId) {
            $d = FundDonation::where('gateway', 'uddoktapay')->find($expectedDonationId);
            if ($d) {
                return $d;
            }
        }

        return null;
    }
}
