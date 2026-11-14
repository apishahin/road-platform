<?php

namespace App\Http\Controllers;

use App\Models\FundDonation;
use App\Services\BkashCheckout;
use App\Services\FundPayments;
use App\Services\Sslcommerz;
use App\Services\UddoktaPay;
use App\Support\Funding;
use App\Support\PaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FundDonateController extends Controller
{
    public function __construct(
        private UddoktaPay $gateway,
        private FundPayments $payments,
        private Sslcommerz $ssl,
        private BkashCheckout $bkash,
    ) {
    }

    /** Start an online donation with the configured provider. */
    public function store(Request $request)
    {
        $campaign = Funding::active();
        abort_unless($campaign, 404);

        if (! PaySettings::ready()) {
            return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_error')]);
        }

        $min = PaySettings::minAmount();
        $data = $request->validate([
            'amount'        => ['required', 'integer', 'min:' . $min, 'max:1000000'],
            'donor_name'    => ['nullable', 'string', 'max:120'],
            'donor_contact' => ['nullable', 'string', 'max:150'],
        ]);

        $name = trim((string) ($data['donor_name'] ?? ''));
        $contact = trim((string) ($data['donor_contact'] ?? ''));

        $d = FundDonation::create([
            'campaign_id'   => $campaign->id,
            'amount'        => (int) $data['amount'],
            'status'        => 'pending',
            'gateway'       => PaySettings::provider(),
            'donor_name'    => $name !== '' ? $name : null,
            'donor_contact' => $contact !== '' ? $contact : null,
            'show_name'     => $name !== '' && $request->boolean('show_name'),
            'hide_amount'   => $request->boolean('hide_amount'),
        ]);
        // Our own reference (works as SSLCommerz tran_id and bKash merchantInvoiceNumber).
        $d->forceFill(['tran_id' => 'RPF' . str_pad((string) $d->id, 8, '0', STR_PAD_LEFT)])->save();

        $email = filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact
            : 'donor' . $d->id . '@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'road-platform.local');

        session(['pay_donation_id' => $d->id]);

        return match (PaySettings::provider()) {
            'sslcommerz' => $this->startSslcommerz($d, $campaign, $name, $contact, $email),
            'bkash'      => $this->startBkash($d),
            default      => $this->startUddoktapay($d, $name, $email),
        };
    }

    // ---------------- UddoktaPay ----------------

    private function startUddoktapay(FundDonation $d, string $name, string $email)
    {
        $r = $this->gateway->createCharge([
            'full_name'    => $name !== '' ? $name : 'Donor',
            'email'        => $email,
            'amount'       => (string) $d->amount,
            'metadata'     => ['donation_id' => (string) $d->id, 'ref' => $d->tran_id],
            'redirect_url' => route('fund.pay.callback'),
            'cancel_url'   => route('fund.show') . '#donate',
            'webhook_url'  => url('/api/fund/uddoktapay/webhook'),
        ]);

        if (! $r['ok']) {
            return $this->startFailed($d, $r['message'] ?? null);
        }

        return redirect()->away($r['payment_url']);
    }

    /** Donor returns here from UddoktaPay (GET ?invoice_id=...). */
    public function callback(Request $request)
    {
        $invoice = trim((string) $request->query('invoice_id', ''));
        $expected = (int) session('pay_donation_id', 0) ?: null;
        session()->forget('pay_donation_id');

        if ($invoice === '') {
            return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]);
        }

        $r = $this->payments->settle($invoice, $expected, 'callback');

        return $this->flashBack($r['result']);
    }

    /** UddoktaPay IPN/webhook: take the invoice id, then verify server-side. */
    public function webhook(Request $request)
    {
        $invoice = trim((string) ($request->input('invoice_id') ?? ''));
        if ($invoice === '') {
            return response()->json(['ok' => false], 400);
        }

        $r = $this->payments->settle($invoice, null, 'webhook');

        return response()->json(['ok' => true, 'result' => $r['result']]);
    }

    // ---------------- SSLCommerz ----------------

    private function startSslcommerz(FundDonation $d, $campaign, string $name, string $contact, string $email)
    {
        $r = $this->ssl->init([
            'total_amount' => (string) $d->amount,
            'tran_id'      => $d->tran_id,
            'success_url'  => route('fund.pay.ssl.success'),
            'fail_url'     => route('fund.pay.ssl.fail'),
            'cancel_url'   => route('fund.pay.ssl.cancel'),
            'ipn_url'      => url('/api/fund/sslcommerz/ipn'),
            'cus_name'     => $name !== '' ? $name : 'Donor',
            'cus_email'    => $email,
            'cus_add1'     => 'Dhaka',
            'cus_phone'    => $contact !== '' ? $contact : '01700000000',
            'value_a'      => (string) $d->id,
        ]);

        if (! $r['ok']) {
            return $this->startFailed($d, $r['message'] ?? null);
        }

        return redirect()->away($r['gateway_url']);
    }

    /** SSLCommerz POSTs the donor here on success. */
    public function sslSuccess(Request $request)
    {
        $r = $this->settleSsl($request, 'callback');

        return $this->flashBack($r);
    }

    public function sslFail(Request $request)
    {
        $this->failByTranId((string) $request->input('tran_id', ''));

        return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]);
    }

    public function sslCancel(Request $request)
    {
        $this->failByTranId((string) $request->input('tran_id', ''));

        return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]);
    }

    /** SSLCommerz IPN (server-to-server). */
    public function sslIpn(Request $request)
    {
        $result = $this->settleSsl($request, 'ipn');

        return response()->json(['ok' => true, 'result' => $result]);
    }

    private function settleSsl(Request $request, string $source): string
    {
        session()->forget('pay_donation_id');
        $tranId = trim((string) $request->input('tran_id', ''));
        $valId = trim((string) $request->input('val_id', ''));

        $d = FundDonation::where('gateway', 'sslcommerz')->where('tran_id', $tranId)->first();
        if (! $d || $valId === '') {
            return $d ? 'failed' : 'unknown';
        }

        $v = $this->ssl->validatePayment($valId);
        if ($v === null) {
            return 'error'; // gateway unreachable: donor can retry the return URL
        }

        if ($d->invoice_id !== $valId) {
            $d->forceFill(['invoice_id' => $valId])->save();
        }

        if ($v['valid'] && $v['tran_id'] === $tranId && $v['amount'] === (int) $d->amount) {
            $this->payments->markPaid($d, $v['bank_tran_id'], [
                'val_id' => $valId, 'method' => $v['card_type'] ?: 'SSLCommerz', 'source' => $source,
            ]);

            return 'paid';
        }

        Log::warning('SSLCommerz validation failed', ['tran' => $tranId, 'val' => $valId]);
        $this->payments->markFailed($d);

        return 'failed';
    }

    // ---------------- bKash ----------------

    private function startBkash(FundDonation $d)
    {
        $r = $this->bkash->create($d, route('fund.pay.bkash.callback'));

        if (! $r['ok']) {
            return $this->startFailed($d, $r['message'] ?? null);
        }

        $d->forceFill(['invoice_id' => $r['payment_id']])->save();

        return redirect()->away($r['bkash_url']);
    }

    /** bKash returns the donor here: GET ?paymentID=...&status=success|failure|cancel. */
    public function bkashCallback(Request $request)
    {
        session()->forget('pay_donation_id');
        $pid = trim((string) $request->query('paymentID', ''));
        $status = strtolower((string) $request->query('status', ''));

        $d = FundDonation::where('gateway', 'bkash')->where('invoice_id', $pid)->first();
        if (! $d) {
            return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]);
        }

        if ($status !== 'success') {
            $this->payments->markFailed($d);

            return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]);
        }

        $exec = $this->bkash->execute($pid);
        if ($exec === null) {
            return redirect()->route('fund.show')->with('fund_flash', ['warn', __('issues.fund_pay_pending')]);
        }

        if ($exec['ok'] && $exec['amount'] === (int) $d->amount) {
            $this->payments->markPaid($d, $exec['trx_id'], ['invoice' => $pid, 'method' => 'bKash', 'source' => 'callback']);

            return redirect()->route('fund.show')->with('fund_flash', ['ok', __('issues.fund_pay_success')]);
        }

        Log::warning('bKash execute rejected', ['pid' => $pid, 'exec' => $exec]);
        $this->payments->markFailed($d);

        return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]);
    }

    // ---------------- shared ----------------

    private function startFailed(FundDonation $d, ?string $why)
    {
        $this->payments->markFailed($d);
        Log::warning('Donation checkout failed', ['donation' => $d->id, 'gateway' => $d->gateway, 'why' => $why]);
        session()->forget('pay_donation_id');

        return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_error')])->withFragment('donate');
    }

    private function failByTranId(string $tranId): void
    {
        if ($tranId === '') {
            return;
        }
        $d = FundDonation::where('gateway', 'sslcommerz')->where('tran_id', $tranId)->first();
        if ($d) {
            $this->payments->markFailed($d);
        }
    }

    private function flashBack(string $result)
    {
        return match ($result) {
            'paid'    => redirect()->route('fund.show')->with('fund_flash', ['ok', __('issues.fund_pay_success')]),
            'pending', 'error' => redirect()->route('fund.show')->with('fund_flash', ['warn', __('issues.fund_pay_pending')]),
            default   => redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]),
        };
    }
}
