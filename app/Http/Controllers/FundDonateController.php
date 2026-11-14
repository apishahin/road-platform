<?php

namespace App\Http\Controllers;

use App\Models\FundDonation;
use App\Services\FundPayments;
use App\Services\UddoktaPay;
use App\Support\Funding;
use App\Support\PaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FundDonateController extends Controller
{
    public function __construct(private UddoktaPay $gateway, private FundPayments $payments) {}

    /** Start an online donation: create a pending row, then send the donor to UddoktaPay. */
    public function store(Request $request)
    {
        $campaign = Funding::active();
        abort_unless($campaign, 404);

        if (! PaySettings::ready()) {
            return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_error')]);
        }

        $min = PaySettings::minAmount();
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$min, 'max:1000000'],
            'donor_name' => ['nullable', 'string', 'max:120'],
            'donor_contact' => ['nullable', 'string', 'max:150'],
        ]);

        $name = trim((string) ($data['donor_name'] ?? ''));
        $contact = trim((string) ($data['donor_contact'] ?? ''));

        $d = FundDonation::create([
            'campaign_id' => $campaign->id,
            'amount' => (int) $data['amount'],
            'status' => 'pending',
            'gateway' => 'uddoktapay',
            'donor_name' => $name !== '' ? $name : null,
            'donor_contact' => $contact !== '' ? $contact : null,
            'show_name' => $name !== '' && $request->boolean('show_name'),
            'hide_amount' => $request->boolean('hide_amount'),
        ]);
        // Our own reference, useful for support even before the gateway answers.
        $d->forceFill(['tran_id' => 'RPF'.str_pad((string) $d->id, 8, '0', STR_PAD_LEFT)])->save();

        // UddoktaPay requires an email; use the donor's one if given, else a
        // syntactically valid placeholder on our own host.
        $email = filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact
            : 'donor'.$d->id.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'road-platform.local');

        $r = $this->gateway->createCharge([
            'full_name' => $name !== '' ? $name : 'Donor',
            'email' => $email,
            'amount' => (string) $d->amount,
            'metadata' => ['donation_id' => (string) $d->id, 'ref' => $d->tran_id],
            'redirect_url' => route('fund.pay.callback'),
            'cancel_url' => route('fund.show').'#donate',
            'webhook_url' => url('/api/fund/uddoktapay/webhook'),
        ]);

        if (! $r['ok']) {
            $d->forceFill(['status' => 'failed'])->save();
            Log::warning('Donation checkout failed', ['donation' => $d->id, 'why' => $r['message'] ?? null]);

            return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_error')])->withFragment('donate');
        }

        session(['pay_donation_id' => $d->id]);

        return redirect()->away($r['payment_url']);
    }

    /** Donor returns here from the gateway (GET ?invoice_id=...). */
    public function callback(Request $request)
    {
        $invoice = trim((string) $request->query('invoice_id', ''));
        $expected = (int) session('pay_donation_id', 0) ?: null;
        session()->forget('pay_donation_id');

        if ($invoice === '') {
            return redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]);
        }

        $r = $this->payments->settle($invoice, $expected, 'callback');

        return match ($r['result']) {
            'paid' => redirect()->route('fund.show')->with('fund_flash', ['ok', __('issues.fund_pay_success')]),
            'pending' => redirect()->route('fund.show')->with('fund_flash', ['warn', __('issues.fund_pay_pending')]),
            default => redirect()->route('fund.show')->with('fund_flash', ['err', __('issues.fund_pay_failed')]),
        };
    }

    /**
     * Gateway IPN/webhook (JSON or form POST). We only take the invoice id
     * from it, then ask the gateway ourselves for the real state.
     */
    public function webhook(Request $request)
    {
        $invoice = trim((string) ($request->input('invoice_id') ?? ''));
        if ($invoice === '') {
            return response()->json(['ok' => false], 400);
        }

        $r = $this->payments->settle($invoice, null, 'webhook');

        // Always 200 once accepted: a 5xx would make the gateway retry forever.
        return response()->json(['ok' => true, 'result' => $r['result']]);
    }
}
