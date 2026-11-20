<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsGateway;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GatewaySender;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class SmsGatewaysController extends Controller
{
    public const BLANK = [
        'name' => '', 'channel' => 'sms', 'method' => 'GET', 'url' => 'https://',
        'body_type' => 'query', 'params' => '', 'headers' => '', 'sender_id' => '',
        'success_marker' => '', 'priority' => 10, 'enabled' => false,
    ];

    public const BULKSMSBD = [
        'name' => 'BulkSMSBD', 'channel' => 'sms', 'method' => 'GET',
        'url' => 'https://bulksmsbd.net/api/smsapi', 'body_type' => 'query',
        'params' => '{"api_key":"{api_key}","type":"text","number":"{to}","senderid":"{sender_id}","message":"{message}"}',
        'success_marker' => '202',
    ];

    /** One9 SMS Primary SMS Sending API, documented at sms.one9.one. */
    public const ONE9SMS = [
        'name' => 'One9 SMS', 'channel' => 'sms', 'method' => 'POST',
        'url' => 'https://sms.one9.one/sms/api', 'body_type' => 'form',
        'params' => '{"api_key":"{api_key}","to":"{to}","from":"{sender_id}","sms":"{message}"}',
        'headers' => '{"Accept":"application/json"}',
        // One9's example uses `sms` as the default sender. It can be changed
        // to an approved Sender ID by the admin when the gateway is saved.
        'sender_id' => 'sms',
        // Any successful HTTP 2xx response is accepted; do not depend on a
        // provider response string that could change.
        'success_marker' => '',
    ];

    private function guard(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'admin', 403);

        return $u;
    }

    public function index(Request $request)
    {
        // The gateway editor lives on the login-settings page now.
        return redirect(route('admin.loginsettings.index', $request->only(['edit', 'preset'])) . '#gateways');
    }

    public function save(Request $request)
    {
        $admin = $this->guard($request);

        $v = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'name' => 'required|string|max:80',
            'channel' => 'required|in:sms,whatsapp',
            'method' => 'required|in:GET,POST',
            'url' => 'required|string|max:500',
            'body_type' => 'required|in:query,json,form',
            'params' => 'nullable|string|max:3000',
            'headers' => 'nullable|string|max:3000',
            'sender_id' => 'nullable|string|max:60',
            'success_marker' => 'nullable|string|max:100',
            'priority' => 'required|integer|min:1|max:999',
            'api_key' => 'nullable|string|max:500',
        ]);
        // Never flash the API key back into the session.
        $keep = $request->except(['api_key']);
        if ($v->fails()) {
            return back()->withInput($keep)->withErrors($v);
        }
        $d = $v->validated();

        $url = trim((string) $d['url']);
        if (!str_starts_with(strtolower($url), 'https://')) {
            return back()->withInput($keep)->withErrors(['gw' => __('smsgw.bad_url')]);
        }
        foreach (['params', 'headers'] as $k) {
            $s = trim((string) ($d[$k] ?? ''));
            if ($s !== '' && !is_array(json_decode($s, true))) {
                return back()->withInput($keep)->withErrors(['gw' => __('smsgw.bad_json')]);
            }
        }

        $g = !empty($d['id']) ? SmsGateway::findOrFail((int) $d['id']) : new SmsGateway();
        $g->fill([
            'name' => trim((string) $d['name']),
            'channel' => $d['channel'],
            'enabled' => $request->boolean('enabled'),
            'priority' => (int) $d['priority'],
            'method' => $d['method'],
            'url' => $url,
            'body_type' => $d['body_type'],
            'params' => trim((string) ($d['params'] ?? '')) ?: null,
            'headers' => trim((string) ($d['headers'] ?? '')) ?: null,
            'sender_id' => trim((string) ($d['sender_id'] ?? '')) ?: null,
            'success_marker' => trim((string) ($d['success_marker'] ?? '')) ?: null,
        ]);
        if (($d['api_key'] ?? '') !== '') {
            $g->setApiKey((string) $d['api_key']);
        } elseif ($request->boolean('clear_key')) {
            $g->setApiKey(null);
        }
        $g->save();

        app(AuditService::class)->log($admin, 'sms_gateway.save', 'sms_gateway', (string) $g->id, [
            'name' => $g->name, 'channel' => $g->channel, 'enabled' => $g->enabled,
        ]);

        return redirect(route('admin.loginsettings.index') . '#gateways')->with('gw_ok', __('smsgw.saved'));
    }

    public function toggle(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $g = SmsGateway::findOrFail($id);
        $g->enabled = !$g->enabled;
        $g->save();
        app(AuditService::class)->log($admin, 'sms_gateway.toggle', 'sms_gateway', (string) $g->id, [
            'name' => $g->name, 'enabled' => $g->enabled,
        ]);

        return redirect(route('admin.loginsettings.index') . '#gateways')->with('gw_ok', __('smsgw.toggled'));
    }

    public function delete(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $g = SmsGateway::findOrFail($id);
        $name = $g->name;
        $g->delete();
        app(AuditService::class)->log($admin, 'sms_gateway.delete', 'sms_gateway', (string) $id, ['name' => $name]);

        return redirect(route('admin.loginsettings.index') . '#gateways')->with('gw_ok', __('smsgw.deleted'));
    }

    public function test(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $g = SmsGateway::findOrFail($id);

        $key = 'gwtest:' . $admin->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['gw' => __('smsgw.test_wait')]);
        }
        $phone = Phone::normalize((string) $request->input('phone'));
        if (!$phone) {
            return back()->withErrors(['gw' => __('smsgw.bad_phone')]);
        }
        RateLimiter::hit($key, 600);

        [$ok, $note] = (new GatewaySender())->attempt($g, $phone, 'Road Platform test message.');
        app(AuditService::class)->log($admin, 'sms_gateway.test', 'sms_gateway', (string) $g->id, [
            'name' => $g->name, 'ok' => $ok,
        ]);

        return back()->with($ok ? 'gw_ok' : 'gw_fail', ($ok ? __('smsgw.test_ok') : __('smsgw.test_fail')) . ' ' . $note);
    }
}