<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Services\GatewaySender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayNestedParamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_json_placeholders_are_filled(): void
    {
        Http::fake(['*' => Http::response('{"ok":true}', 200)]);
        $g = new SmsGateway([
            'name' => 'W', 'channel' => 'whatsapp', 'enabled' => true, 'priority' => 1,
            'method' => 'POST', 'url' => 'https://w.test/send', 'body_type' => 'json',
            'params' => '{"to":"{to}","template":{"name":"otp","components":[{"text":"{message}"}]}}',
            'headers' => '{"Authorization":"Bearer {api_key}"}',
            'success_marker' => 'ok',
        ]);
        $g->setApiKey('KEY1');
        $g->save();

        $r = (new GatewaySender())->send('+8801712345678', 'Hi', 'whatsapp');
        $this->assertTrue($r['ok']);
        Http::assertSent(fn ($req) => ($req->data()['to'] ?? null) === '8801712345678'
            && ($req->data()['template']['components'][0]['text'] ?? null) === 'Hi'
            && $req->hasHeader('Authorization', 'Bearer KEY1'));
    }
}