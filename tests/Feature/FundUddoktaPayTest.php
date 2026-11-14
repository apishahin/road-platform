<?php

namespace Tests\Feature;

use App\Models\FundCampaign;
use App\Models\FundDonation;
use App\Support\Funding;
use App\Support\PaySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FundUddoktaPayTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(int $goal = 10000): FundCampaign
    {
        return FundCampaign::create([
            'title' => 'Test fund', 'goal_amount' => $goal,
            'is_active' => true, 'show_donors' => true,
        ]);
    }

    private function gatewayOn(): void
    {
        PaySettings::save([
            'up_enabled' => true,
            'up_base_url' => 'https://pay.test',
            'up_api_key' => 'key-123',
        ]);
    }

    private function fakeCheckout(string $paymentUrl = 'https://pay.test/checkout/INV111'): void
    {
        Http::fake([
            'https://pay.test/api/checkout-v2' => Http::response(['status' => true, 'payment_url' => $paymentUrl]),
        ]);
    }

    private function fakeVerify(array $over = []): void
    {
        Http::fake([
            'https://pay.test/api/verify-payment' => Http::response(array_merge([
                'status' => 'COMPLETED', 'invoice_id' => 'INV111', 'amount' => 100,
                'transaction_id' => 'TRX9', 'payment_method' => 'bKash',
                'metadata' => [],
            ], $over)),
        ]);
    }

    public function test_donate_form_hidden_until_gateway_ready(): void
    {
        $this->campaign();
        $this->get('/fund')->assertOk()->assertDontSee('/fund/donate', false);

        $this->gatewayOn();
        $this->get('/fund')->assertOk()->assertSee('/fund/donate', false);
    }

    public function test_store_creates_pending_donation_and_redirects_to_gateway(): void
    {
        URL::forceRootUrl('https://road.test');
        $this->campaign();
        $this->gatewayOn();
        $this->fakeCheckout();

        $r = $this->post('/fund/donate', ['amount' => 100, 'donor_name' => 'Karim', 'show_name' => '1']);

        $r->assertRedirect('https://pay.test/checkout/INV111');
        $d = FundDonation::sole();
        $this->assertSame('pending', $d->status);
        $this->assertSame('uddoktapay', $d->gateway);
        $this->assertSame(100, $d->amount);
        $this->assertTrue($d->show_name);

        // The gateway call carried our key, amount and metadata.
        Http::assertSent(function ($req) use ($d) {
            $j = $req->data();

            return $req->hasHeader('RT-UDDOKTAPAY-API-KEY', 'key-123')
                && $req->url() === 'https://pay.test/api/checkout-v2'
                && $j['amount'] === '100'
                && $j['metadata']['donation_id'] === (string) $d->id
                && $j['return_type'] === 'GET'
                && $j['redirect_url'] === 'http://road.test/fund/donate/callback';
        });
    }

    public function test_store_below_minimum_is_rejected(): void
    {
        $this->campaign();
        $this->gatewayOn();
        $this->fakeCheckout();

        $this->post('/fund/donate', ['amount' => 5])->assertSessionHasErrors('amount');
        $this->assertSame(0, FundDonation::count());
    }

    public function test_store_fails_cleanly_when_gateway_unreachable(): void
    {
        $this->campaign();
        $this->gatewayOn();
        Http::fake(['https://pay.test/api/checkout-v2' => Http::response(['status' => false, 'message' => 'bad key'], 401)]);

        $this->post('/fund/donate', ['amount' => 100])->assertRedirect(url('/fund').'#donate');
        $this->assertSame('failed', FundDonation::sole()->status);
    }

    public function test_callback_marks_completed_payment_as_paid(): void
    {
        $this->campaign();
        $this->gatewayOn();
        $d = FundDonation::create(['campaign_id' => 1, 'amount' => 100, 'status' => 'pending', 'gateway' => 'uddoktapay']);
        $this->fakeVerify(['metadata' => ['donation_id' => (string) $d->id]]);

        $this->get('/fund/donate/callback?invoice_id=INV111')->assertRedirect('/fund');

        $d->refresh();
        $this->assertSame('paid', $d->status);
        $this->assertSame('TRX9', $d->tran_id);
        $this->assertSame('INV111', $d->invoice_id);
        $this->assertNotNull($d->paid_at);
        $this->assertSame(100, Funding::raised($d->campaign));
    }

    public function test_callback_uses_session_fallback_when_metadata_missing(): void
    {
        $this->campaign();
        $this->gatewayOn();
        $d = FundDonation::create(['campaign_id' => 1, 'amount' => 100, 'status' => 'pending', 'gateway' => 'uddoktapay']);
        $this->fakeVerify(['metadata' => []]);

        $this->withSession(['pay_donation_id' => $d->id])
            ->get('/fund/donate/callback?invoice_id=INV111');

        $this->assertSame('paid', $d->fresh()->status);
    }

    public function test_callback_with_pending_gateway_status_keeps_pending(): void
    {
        $this->campaign();
        $this->gatewayOn();
        $d = FundDonation::create(['campaign_id' => 1, 'amount' => 100, 'status' => 'pending', 'gateway' => 'uddoktapay']);
        $this->fakeVerify(['status' => 'PENDING', 'metadata' => ['donation_id' => (string) $d->id]]);

        $this->get('/fund/donate/callback?invoice_id=INV111');

        $this->assertSame('pending', $d->fresh()->status);
        $this->assertSame(0, Funding::raised($d->campaign));
    }

    public function test_callback_with_error_status_marks_failed(): void
    {
        $this->campaign();
        $this->gatewayOn();
        $d = FundDonation::create(['campaign_id' => 1, 'amount' => 100, 'status' => 'pending', 'gateway' => 'uddoktapay']);
        $this->fakeVerify(['status' => 'ERROR', 'metadata' => ['donation_id' => (string) $d->id]]);

        $this->get('/fund/donate/callback?invoice_id=INV111');

        $this->assertSame('failed', $d->fresh()->status);
    }

    public function test_paid_amount_mismatch_is_not_counted(): void
    {
        $this->campaign();
        $this->gatewayOn();
        $d = FundDonation::create(['campaign_id' => 1, 'amount' => 100, 'status' => 'pending', 'gateway' => 'uddoktapay']);
        $this->fakeVerify(['amount' => 10, 'metadata' => ['donation_id' => (string) $d->id]]); // paid less!

        $this->get('/fund/donate/callback?invoice_id=INV111');

        $this->assertSame('pending', $d->fresh()->status);
        $this->assertSame(0, Funding::raised($d->campaign));
    }

    public function test_webhook_marks_paid_and_is_idempotent(): void
    {
        $this->campaign();
        $this->gatewayOn();
        $d = FundDonation::create(['campaign_id' => 1, 'amount' => 100, 'status' => 'pending', 'gateway' => 'uddoktapay']);
        $this->fakeVerify(['metadata' => ['donation_id' => (string) $d->id]]);

        $this->postJson('/api/fund/uddoktapay/webhook', ['invoice_id' => 'INV111'])->assertOk();
        $this->assertSame('paid', $d->fresh()->status);

        // Second delivery must not double-count or error.
        $firstPaidAt = $d->fresh()->paid_at;
        $this->postJson('/api/fund/uddoktapay/webhook', ['invoice_id' => 'INV111'])->assertOk();
        $this->assertEquals($firstPaidAt, $d->fresh()->paid_at);
        $this->assertSame(100, Funding::raised($d->campaign));
    }

    public function test_webhook_without_invoice_is_rejected(): void
    {
        $this->postJson('/api/fund/uddoktapay/webhook', [])->assertStatus(400);
    }
}
