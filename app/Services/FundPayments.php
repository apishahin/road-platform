<?php

namespace App\Services;

use App\Models\FundDonation;
use Illuminate\Support\Facades\Log;

/**
 * Donation settlement helpers shared by every gateway AND the UddoktaPay
 * invoice-settlement flow below. Everything here is idempotent.
 */
class FundPayments
{
    public function __construct(
        private UddoktaPay $gateway,
        private AuditService $audit,
    ) {
    }

    /** Mark a donation paid exactly once. Safe to call from callback/IPN/webhook. */
    public function markPaid(FundDonation $d, ?string $trxId, array $details = []): void
    {
        if ($d->status === 'paid') {
            return;
        }

        $d->forceFill([
            'status'  => 'paid',
            'paid_at' => now(),
            'tran_id' => ($trxId !== null && $trxId !== '') ? $trxId : $d->tran_id,
        ])->save();

        $this->audit->log(null, 'fund.paid_online', 'fund_donation', (string) $d->id, array_merge([
            'trx' => $trxId, 'amount' => $d->amount, 'gateway' => $d->gateway,
        ], $details));
    }

    /** Mark a still-pending donation failed (never undoes a paid one). */
    public function markFailed(FundDonation $d): void
    {
        if ($d->status === 'pending') {
            $d->forceFill(['status' => 'failed'])->save();
        }
    }

    /**
     * UddoktaPay: verify an invoice with the gateway and update our donation row.
     * Shared by the browser callback and the webhook/IPN.
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

        if ($donation->invoice_id !== $invoiceId) {
            $donation->forceFill(['invoice_id' => $invoiceId])->save();
        }

        $gwStatus = (string) ($v['status'] ?? '');

        if ($gwStatus === 'COMPLETED') {
            $amount = (int) ($v['amount'] ?? 0);
            if ($amount !== (int) $donation->amount) {
                Log::warning('UddoktaPay: paid amount mismatch', [
                    'invoice' => $invoiceId, 'expected' => $donation->amount, 'got' => $amount,
                ]);

                return ['result' => 'pending', 'donation' => $donation];
            }

            $trx = (string) ($v['transaction_id'] ?? '');
            $this->markPaid($donation, $trx !== '' ? $trx : $invoiceId, [
                'invoice' => $invoiceId,
                'method'  => $v['payment_method'] ?? null,
                'source'  => $source,
            ]);

            return ['result' => 'paid', 'donation' => $donation];
        }

        if ($gwStatus === 'PENDING') {
            return ['result' => 'pending', 'donation' => $donation];
        }

        $this->markFailed($donation);

        return ['result' => 'failed', 'donation' => $donation];
    }

    private function findDonation(array $v, string $invoiceId, ?int $expectedDonationId): ?FundDonation
    {
        $d = FundDonation::where('gateway', 'uddoktapay')->where('invoice_id', $invoiceId)->first();
        if ($d) {
            return $d;
        }

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

        if ($expectedDonationId) {
            $d = FundDonation::where('gateway', 'uddoktapay')->find($expectedDonationId);
            if ($d) {
                return $d;
            }
        }

        return null;
    }
}
