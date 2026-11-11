<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Services\SmsSender;
use App\Support\LoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsSenderTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $p): void
    {
        LoginSettings::save(array_merge(LoginSettings::defaults(), ['otp_provider' => $p]));
    }

    private function gw(string $name, string $channel, string $host, int $prio = 1): void
    {
        $g = new SmsGateway([
            'name' => $name,
            'channel' => $channel,
            'enabled' => true,
            'priority' => $prio,
            'method' => 'GET',
            'url' => 'https://' . $host . '/send',
            'body_type' => 'query',
            'params' => '{"api_key":"{api_key}","number":"{to}","message":"{message}"}',
            'success_marker' => '202',
        ]);
        $g->setApiKey('KEY1');
        $g->save();
    }

    public function test_log_provider_does_not_call_gateways(): void
    {
        Http::fake(['*' => Http::response('202', 200)]);
        $this->provider('log');
        $this->gw('S', 'sms', 's.test');
        (new SmsSender())->send('+8801712345678', 'Hi');
        Http::assertNothingSent();
    }

    public function test_gateway_provider_tries_whatsapp_first(): void
    {
        Http::fake(['*' => Http::response('202', 200)]);
        $this->provider('gateway');
        $this->gw('S', 'sms', 's.test');
        $this->gw('W', 'whatsapp', 'w.test');
        $ok = (new SmsSender())->send('+8801712345678', 'Hi');
        $this->assertTrue($ok);
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'w.test'));
    }

    public function test_falls_to_sms_when_whatsapp_fails(): void
    {
        Http::fake([
            'https://w.test/*' => Http::response('1007', 200),
            'https://s.test/*' => Http::response('202', 200),
        ]);
        $this->provider('gateway');
        $this->gw('S', 'sms', 's.test');
        $this->gw('W', 'whatsapp', 'w.test');
        $ok = (new SmsSender())->send('+8801712345678', 'Hi');
        $this->assertTrue($ok);
        Http::assertSentCount(2);
    }

    public function test_returns_false_when_all_fail(): void
    {
        Http::fake(['*' => Http::response('1007', 200)]);
        $this->provider('gateway');
        $this->gw('S', 'sms', 's.test');
        $this->assertFalse((new SmsSender())->send('+8801712345678', 'Hi'));
    }

    public function test_returns_false_when_none_enabled(): void
    {
        Http::fake(['*' => Http::response('202', 200)]);
        $this->provider('gateway');
        $this->assertFalse((new SmsSender())->send('+8801712345678', 'Hi'));
        Http::assertNothingSent();
    }
}