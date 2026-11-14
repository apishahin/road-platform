<?php

namespace Tests\Feature;

use App\Models\FundCampaign;
use App\Models\FundDonation;
use App\Support\PaySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FundGatewaysTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(): FundCampaign
    {
        return FundCampaign::create([
            'title' => 'Test fund', 'goal_amount' => 10000,
            'is_active' => true, 'show_donors' => true,
        ]);
    }

    private function sslOn(): void
    {
        PaySettings::save([
            'provider' => 'sslcommerz',
            'ssl_store_id' => 'store123', 'ssl_store_passwd' => 'pass123', 'ssl_sandbox' => true,
        ]);
    }

    private function bkOn(): void
    {
        PaySettings::save([
            'provider' => 'bkash',
            'bk_app_key' => 'ak', 'bk_app_secret' => 'as',
            'bk_username' => 'user', 'bk_password' => 'pw', 'bk_sandbox' => true,
        ]);
    }

    // ---------- provider switching ----------

    public function test_provider_none_means_no_donate_button(): void
    {
        $this->campaign();
        PaySettings::save(['provider' => 'none']);
        $this->get('/fund')->assertOk()->assertDontSee('/fund/donate', false);
    }

    public function test_sslcommerz_ready_with_credentials(): void
    {
        $this->campaign();
        $this->sslOn();
        $this->get('/fund')->assertOk()->assertSee('/fund/donate', false);
        $this->assertSame('sslcommerz', PaySettings::provider());
        $this->assertTrue(PaySettings::ready());
    }

    public function test_incomplete_bkash_config_is_not_ready(): void
    {
        PaySettings::save(['provider' => 'bkash', 'bk_app_key' => 'ak']);
        $this->assertFalse(PaySettings::ready());
    }

    public function test_legacy_up_enabled_migrates_to_provider(): void
    {
        PaySettings::save(['up_enabled' => true, 'up_base_url' => 'https://pay.test', 'up_api_key' => 'k']);
        $this->assertSame('uddoktapay', PaySettings::provider());
        $this->assertTrue(PaySettings::ready());
    }

    public function test_off_switch_respects_provider_and_up_button_hidden(): void
    {
        $this->campaign();
        $this->sslOn();

        $res = $this->post('/fund/donate', ['amount' => 100]);
        // wrong gateway base not faked → init fails → flash error, not a fatal
        $this->assertStringContainsString('/fund', (string) $res->headers->get('Location'));
        $res->assertSessionHas('fund_flash', fn ($v) => $v[0] === 'err');
        $this->assertSame(0, FundDonation::where('status', 'paid')->count());
    }

    // ---------- SSLCommerz flow ----------

    private function fakeSslInit(): void
    {
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/gw-abc',
            ]),
        ]);
    }

    private function fakeSslValidate(array $over = []): void
    {
        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response(array_merge([
                'status' => 'VALID', 'val_id' => 'VAL9', 'tran_id' => '',
                'currency_amount' => '100.00', 'amount' => '100.00',
                'bank_tran_id' => 'BANK1', 'card_type' => 'bKash-bKash',
            ], $over)),
        ]);
    }

    public function test_sslcommerz_checkout_redirects_to_gateway_page()
    {
        $this->campaign();
        $this->sslOn();
        $this->fakeSslInit();

        $res = $this->post('/fund/donate', ['amount' => 100, 'donor_name' => 'Rahim']);
        $res->assertRedirect('https://sandbox.sslcommerz.com/EasyCheckOut/gw-abc');

        $d = FundDonation::firstOrFail();
        $this->assertSame('sslcommerz', $d->gateway);
        $this->assertSame('pending', $d->status);
        $this->assertMatchesRegularExpression('/^RPF\d{8}$/', $d->tran_id);
    }

    public function test_sslcommerz_success_with_valid_val_id_marks_paid(): void
    {
        $this->campaign();
        $this->sslOn();

        $d = FundDonation::create([
            'campaign_id' => FundCampaign::firstOrFail()->id, 'amount' => 100,
            'status' => 'pending', 'gateway' => 'sslcommerz',
            'tran_id' => 'RPF00000055','show_name' => false,
        ]);
        $this->fakeSslValidate(['tran_id' => $d->tran_id]);

        $res = $this->post(route('fund.pay.ssl.success'), [
            'tran_id' => $d->tran_id, 'val_id' => 'VAL9', 'status' => 'VALID',
        ]);
        $res->assertRedirect('/fund');
        $res->assertSessionHas('fund_flash', ['ok', __('issues.fund_pay_success')]);

        $d->refresh();
        $this->assertSame('paid', $d->status);
        $this->assertSame('VAL9', $d->invoice_id);

        // idempotent second hit
        $this->post(route('fund.pay.ssl.success'), ['tran_id' => $d->tran_id, 'val_id' => 'VAL9']);
        $this->assertSame(100, \App\Support\Funding::raised($d->fresh()->campaign));
    }

    public function test_sslcommerz_amount_mismatch_is_not_paid(): void
    {
        $this->campaign();
        $this->sslOn();
        $d = FundDonation::create([
            'campaign_id' => FundCampaign::firstOrFail()->id, 'amount' => 100,
            'status' => 'pending', 'gateway' => 'sslcommerz',
            'tran_id' => 'RPF00000056','show_name' => false,
        ]);
        $this->fakeSslValidate(['tran_id' => $d->tran_id, 'currency_amount' => '50.00', 'amount' => '50.00']);

        $res = $this->post(route('fund.pay.ssl.success'), ['tran_id' => $d->tran_id, 'val_id' => 'VAL9']);
        $res->assertSessionHas('fund_flash', fn ($v) => $v[0] === 'err');
        $this->assertSame('failed', $d->fresh()->status);
    }

    public function test_sslcommerz_cancel_and_fail_mark_failed(): void
    {
        $this->campaign();
        $this->sslOn();
        $d = FundDonation::create([
            'campaign_id' => FundCampaign::firstOrFail()->id, 'amount' => 100,
            'status' => 'pending', 'gateway' => 'sslcommerz',
            'tran_id' => 'RPF00000057','show_name' => false,
        ]);
        $this->post(route('fund.pay.ssl.cancel'), ['tran_id' => $d->tran_id])
            ->assertSessionHas('fund_flash', fn ($v) => $v[0] === 'err');
        $this->assertSame('failed', $d->fresh()->status);
    }

    public function test_sslcommerz_ipn_marks_paid(): void
    {
        $this->campaign();
        $this->sslOn();
        $d = FundDonation::create([
            'campaign_id' => FundCampaign::firstOrFail()->id, 'amount' => 100,
            'status' => 'pending', 'gateway' => 'sslcommerz',
            'tran_id' => 'RPF00000058','show_name' => false,
        ]);
        $this->fakeSslValidate(['tran_id' => $d->tran_id]);

        $this->postJson('/api/fund/sslcommerz/ipn', ['tran_id' => $d->tran_id, 'val_id' => 'VAL9'])
            ->assertOk()->assertJsonPath('result', 'paid');
        $this->assertSame('paid', $d->fresh()->status);
    }

    // ---------- bKash flow ----------

    private function fakeBkTokenAndCreate(): void
    {
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'statusCode' => '0000', 'id_token' => 'tok-1', 'token_type' => 'Bearer', 'expires_in' => 3599,
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000', 'paymentID' => 'PID77',
                'bkashURL' => 'https://sandbox.bka.sh/pay/PID77', 'statusMessage' => 'Successful',
            ]),
        ]);
    }

    public function test_bkash_checkout_redirects_to_bkash_url(): void
    {
        $this->campaign();
        $this->bkOn();
        $this->fakeBkTokenAndCreate();

        $res = $this->post('/fund/donate', ['amount' => 100]);
        $res->assertRedirect('https://sandbox.bka.sh/pay/PID77');

        $d = FundDonation::firstOrFail();
        $this->assertSame('bkash', $d->gateway);
        $this->assertSame('PID77', $d->invoice_id);
        $this->assertSame('pending', $d->status);
    }

    public function test_bkash_callback_success_executes_and_marks_paid(): void
    {
        $this->campaign();
        $this->bkOn();
        $d = FundDonation::create([
            'campaign_id' => FundCampaign::firstOrFail()->id, 'amount' => 100,
            'status' => 'pending', 'gateway' => 'bkash',
            'tran_id' => 'RPF00000059', 'invoice_id' => 'PID77','show_name' => false,
        ]);

Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'statusCode' => '0000', 'id_token' => 'tok-1', 'token_type' => 'Bearer', 'expires_in' => 3599,
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '0000', 'transactionStatus' => 'Completed',
                'trxID' => 'BKASH9', 'amount' => '100', 'merchantInvoiceNumber' => $d->tran_id,
            ]),
        ]);

        $res = $this->get('/fund/pay/bkash/callback?paymentID=PID77&status=success');
        $res->assertRedirect('/fund');
        $res->assertSessionHas('fund_flash', ['ok', __('issues.fund_pay_success')]);

        $d->refresh();
        $this->assertSame('paid', $d->status);
        $this->assertSame('BKASH9', $d->tran_id);
    }

    public function test_bkash_callback_failure_marks_failed(): void
    {
        $this->campaign();
        $this->bkOn();
        $d = FundDonation::create([
            'campaign_id' => FundCampaign::firstOrFail()->id, 'amount' => 100,
            'status' => 'pending', 'gateway' => 'bkash',
            'tran_id' => 'RPF00000060', 'invoice_id' => 'PID77','show_name' => false,
        ]);
        $this->get('/fund/pay/bkash/callback?paymentID=PID77&status=failure')
            ->assertSessionHas('fund_flash', fn ($v) => $v[0] === 'err');
        $this->assertSame('failed', $d->fresh()->status);
    }

    public function test_bkash_execute_incomplete_is_not_paid(): void
    {
        $this->campaign();
        $this->bkOn();
        $d = FundDonation::create([
            'campaign_id' => FundCampaign::firstOrFail()->id, 'amount' => 100,
            'status' => 'pending', 'gateway' => 'bkash',
            'tran_id' => 'RPF00000061', 'invoice_id' => 'PID77','show_name' => false,
        ]);
Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'statusCode' => '0000', 'id_token' => 'tok-1', 'token_type' => 'Bearer', 'expires_in' => 3599,
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '2034', 'transactionStatus' => 'Initiated',
            ]),
        ]);
        $this->get('/fund/pay/bkash/callback?paymentID=PID77&status=success')
            ->assertSessionHas('fund_flash', fn ($v) => $v[0] === 'err');
        $this->assertSame('failed', $d->fresh()->status);
    }
}
