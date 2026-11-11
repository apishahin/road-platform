<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsGatewaysPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['phone' => '+8801700000001', 'display_name' => 'A', 'role' => 'admin']);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'BulkSMSBD',
            'channel' => 'sms',
            'method' => 'GET',
            'url' => 'https://bulksmsbd.net/api/smsapi',
            'body_type' => 'query',
            'params' => '{"api_key":"{api_key}","number":"{to}","message":"{message}"}',
            'sender_id' => '8809600000',
            'success_marker' => '202',
            'priority' => 1,
            'api_key' => 'SECRETKEY9',
        ], $o);
    }

    private function makeGw(User $a): SmsGateway
    {
        $this->actingAs($a)->post('/admin/sms-gateways', $this->payload())->assertSessionHasNoErrors();

        return SmsGateway::firstOrFail();
    }

    public function test_guest_and_citizen_are_blocked(): void
    {
        $this->get('/admin/sms-gateways')->assertStatus(302);
        $c = User::create(['phone' => '+8801700000002', 'display_name' => 'C', 'role' => 'citizen']);
        $this->actingAs($c)->get('/admin/sms-gateways')->assertStatus(403);
        $this->actingAs($c)->post('/admin/sms-gateways', $this->payload())->assertStatus(403);
        $this->assertSame(0, SmsGateway::count());
    }

    public function test_page_and_preset_open(): void
    {
        $a = $this->admin();
        $this->actingAs($a)->get('/admin/sms-gateways')->assertOk();
        $this->actingAs($a)->get('/admin/sms-gateways?preset=bulksmsbd')->assertOk()->assertSee('bulksmsbd.net/api/smsapi', false);
    }

    public function test_save_stores_encrypted_key_and_stays_off(): void
    {
        $a = $this->admin();
        $g = $this->makeGw($a);
        $this->assertFalse($g->enabled);
        $this->assertSame('SECRETKEY9', $g->apiKey());
        $this->assertStringNotContainsString('SECRETKEY9', (string) $g->getRawOriginal('api_key_enc'));
        $this->actingAs($a)->get('/admin/sms-gateways?edit=' . $g->id)->assertOk()->assertDontSee('SECRETKEY9');
    }

    public function test_http_url_refused(): void
    {
        $this->actingAs($this->admin())->post('/admin/sms-gateways', $this->payload(['url' => 'http://x.test/a']))
            ->assertSessionHasErrors('gw');
        $this->assertSame(0, SmsGateway::count());
    }

    public function test_bad_json_refused(): void
    {
        $this->actingAs($this->admin())->post('/admin/sms-gateways', $this->payload(['params' => '{not json']))
            ->assertSessionHasErrors('gw');
        $this->assertSame(0, SmsGateway::count());
    }

    public function test_edit_with_empty_key_keeps_key(): void
    {
        $a = $this->admin();
        $g = $this->makeGw($a);
        $this->actingAs($a)->post('/admin/sms-gateways', $this->payload(['id' => $g->id, 'name' => 'Renamed', 'api_key' => '']))
            ->assertSessionHasNoErrors();
        $g = $g->fresh();
        $this->assertSame('Renamed', $g->name);
        $this->assertSame('SECRETKEY9', $g->apiKey());
        $this->assertSame(1, SmsGateway::count());
    }

    public function test_toggle_turns_gateway_on(): void
    {
        $a = $this->admin();
        $g = $this->makeGw($a);
        $this->actingAs($a)->post('/admin/sms-gateways/' . $g->id . '/toggle');
        $this->assertTrue($g->fresh()->enabled);
    }

    public function test_delete_removes_gateway(): void
    {
        $a = $this->admin();
        $g = $this->makeGw($a);
        $this->actingAs($a)->post('/admin/sms-gateways/' . $g->id . '/delete');
        $this->assertSame(0, SmsGateway::count());
    }

    public function test_send_test_works_and_hides_key(): void
    {
        Http::fake(['*' => Http::response('{"response_code":202}', 200)]);
        $a = $this->admin();
        $g = $this->makeGw($a);
        $this->actingAs($a)->post('/admin/sms-gateways/' . $g->id . '/test', ['phone' => '+8801712345678'])
            ->assertSessionHas('gw_ok');
        $this->assertStringNotContainsString('SECRETKEY9', (string) session('gw_ok'));
        $this->assertSame('ok', $g->fresh()->last_status);
    }

    public function test_bad_test_phone_refused(): void
    {
        Http::fake(['*' => Http::response('{"response_code":202}', 200)]);
        $a = $this->admin();
        $g = $this->makeGw($a);
        $this->actingAs($a)->post('/admin/sms-gateways/' . $g->id . '/test', ['phone' => '12345'])
            ->assertSessionHasErrors('gw');
        Http::assertNothingSent();
    }

    public function test_failed_validation_does_not_flash_key(): void
    {
        $this->actingAs($this->admin())->post('/admin/sms-gateways', $this->payload(['url' => 'http://x.test/a']));
        $this->assertSame('BulkSMSBD', session()->getOldInput('name'));
        $this->assertNull(session()->getOldInput('api_key'));
    }
}