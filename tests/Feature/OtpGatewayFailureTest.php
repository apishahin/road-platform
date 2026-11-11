<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtpGatewayFailureTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $p): void
    {
        LoginSettings::save(array_merge(LoginSettings::defaults(), ['otp_provider' => $p]));
    }

    private function gw(): void
    {
        $g = new SmsGateway([
            'name' => 'S', 'channel' => 'sms', 'enabled' => true, 'priority' => 1,
            'method' => 'GET', 'url' => 'https://s.test/send', 'body_type' => 'query',
            'params' => '{"api_key":"{api_key}","number":"{to}","message":"{message}"}',
            'success_marker' => '202',
        ]);
        $g->setApiKey('KEY1');
        $g->save();
    }

    public function test_gateway_failure_shows_error_not_sent(): void
    {
        $this->provider('gateway');
        $this->post('/login', ['phone' => '01712345678'])->assertSessionHasErrors('phone');
    }

    public function test_gateway_failure_with_bad_reply_shows_error(): void
    {
        Http::fake(['*' => Http::response('1007', 200)]);
        $this->provider('gateway');
        $this->gw();
        $this->post('/login', ['phone' => '01712345678'])->assertSessionHasErrors('phone');
    }

    public function test_gateway_success_goes_to_verify(): void
    {
        Http::fake(['*' => Http::response('202', 200)]);
        $this->provider('gateway');
        $this->gw();
        $this->post('/login', ['phone' => '01712345678'])->assertRedirect(route('login.verify'));
    }

    public function test_log_provider_still_goes_to_verify(): void
    {
        $this->provider('log');
        $this->post('/login', ['phone' => '01712345678'])->assertRedirect(route('login.verify'));
    }
}