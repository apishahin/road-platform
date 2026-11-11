<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Services\GatewaySender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewaySenderTest extends TestCase
{
    use RefreshDatabase;

    private function gw(array $o = []): SmsGateway
    {
        $g = new SmsGateway(array_merge([
            'name' => 'A',
            'channel' => 'sms',
            'enabled' => true,
            'priority' => 1,
            'method' => 'GET',
            'url' => 'https://a.test/send',
            'body_type' => 'query',
            'params' => '{"api_key":"{api_key}","number":"{to}","message":"{message}","senderid":"{sender_id}"}',
            'sender_id' => '8801',
            'success_marker' => '202',
        ], $o));
        $g->setApiKey('KEY1');
        $g->save();

        return $g;
    }

    public function test_sends_with_plain_number_and_key(): void
    {
        Http::fake(['*' => Http::response('{"response_code":202}', 200)]);
        $g = $this->gw();
        $r = (new GatewaySender())->send('+8801712345678', 'Code 123456');
        $this->assertTrue($r['ok']);
        Http::assertSent(function ($req) {
            $u = $req->url();
            return str_contains($u, 'number=8801712345678') && str_contains($u, 'api_key=KEY1') && str_contains($u, '123456');
        });
        $this->assertSame('ok', $g->fresh()->last_status);
    }

    public function test_url_template_is_merged_into_query(): void
    {
        Http::fake(['*' => Http::response('{"response_code":202}', 200)]);
        $this->gw(['url' => 'https://a.test/send?api_key={api_key}&number={to}', 'params' => null]);
        $r = (new GatewaySender())->send('+8801712345678', 'Hi');
        $this->assertTrue($r['ok']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api_key=KEY1') && str_contains($req->url(), 'number=8801712345678'));
    }

    public function test_post_json_works(): void
    {
        Http::fake(['*' => Http::response('{"response_code":202}', 200)]);
        $this->gw(['method' => 'POST', 'body_type' => 'json']);
        $r = (new GatewaySender())->send('+8801712345678', 'Hi');
        $this->assertTrue($r['ok']);
        Http::assertSent(fn ($req) => $req->method() === 'POST' && ($req->data()['number'] ?? null) === '8801712345678');
    }

    public function test_falls_back_to_next_gateway(): void
    {
        Http::fake([
            'https://a.test/*' => Http::response('{"response_code":1007}', 200),
            'https://b.test/*' => Http::response('{"response_code":202}', 200),
        ]);
        $a = $this->gw(['name' => 'A', 'priority' => 1]);
        $b = $this->gw(['name' => 'B', 'priority' => 2, 'url' => 'https://b.test/send']);
        $r = (new GatewaySender())->send('+8801712345678', 'Hi');
        $this->assertTrue($r['ok']);
        $this->assertCount(2, $r['tried']);
        $this->assertSame('fail', $a->fresh()->last_status);
        $this->assertSame('ok', $b->fresh()->last_status);
    }

    public function test_skips_disabled_and_reports_none(): void
    {
        Http::fake(['*' => Http::response('{"response_code":202}', 200)]);
        $this->gw(['enabled' => false]);
        $r = (new GatewaySender())->send('+8801712345678', 'Hi');
        $this->assertFalse($r['ok']);
        $this->assertCount(0, $r['tried']);
        Http::assertNothingSent();
    }

    public function test_channel_filter(): void
    {
        Http::fake(['*' => Http::response('{"response_code":202}', 200)]);
        $this->gw(['name' => 'S', 'channel' => 'sms']);
        $this->gw(['name' => 'W', 'channel' => 'whatsapp', 'url' => 'https://w.test/send']);
        $r = (new GatewaySender())->send('+8801712345678', 'Hi', 'whatsapp');
        $this->assertTrue($r['ok']);
        $this->assertSame('W', $r['tried'][0]['name']);
        Http::assertSentCount(1);
    }

    public function test_key_is_encrypted_and_never_in_note(): void
    {
        Http::fake(['*' => Http::response('invalid key KEY1', 200)]);
        $g = $this->gw();
        $raw = DB::table('sms_gateways')->where('id', $g->id)->value('api_key_enc');
        $this->assertNotSame('KEY1', $raw);
        $this->assertStringNotContainsString('KEY1', (string) $raw);
        $r = (new GatewaySender())->send('+8801712345678', 'Hi');
        $this->assertFalse($r['ok']);
        $note = (string) $g->fresh()->last_note;
        $this->assertStringNotContainsString('KEY1', $note);
        $this->assertStringContainsString('***', $note);
    }
}