<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsGateway;
use App\Models\User;
use App\Services\AuditService;
use App\Support\LoginSettings;
use Illuminate\Http\Request;

class LoginSettingsController extends Controller
{
    private function guard(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'admin', 403);

        return $u;
    }

    public function show(Request $request)
    {
        $this->guard($request);

        // SMS/WhatsApp gateway editor lives on this page (moved from the
        // old /admin/sms-gateways page, which redirects here).
        $edit = $request->filled('edit') ? SmsGateway::find((int) $request->query('edit')) : null;

        if ($edit) {
            $form = $edit->only(['id', 'name', 'channel', 'method', 'url', 'body_type', 'params', 'headers', 'sender_id', 'success_marker', 'priority', 'enabled']);
        } else {
            $presets = [
                'bulksmsbd' => SmsGatewaysController::BULKSMSBD,
                'one9' => SmsGatewaysController::ONE9SMS,
            ];
            $form = array_merge(SmsGatewaysController::BLANK, $presets[$request->query('preset')] ?? []);
            $form['id'] = null;
        }

        return view('admin.loginsettings.index', [
            's' => LoginSettings::all(),
            'gateways' => SmsGateway::orderBy('channel')->orderBy('priority')->orderBy('id')->get(),
            'form' => $form,
            'editing' => (bool) $edit,
            'editHasKey' => $edit ? $edit->hasKey() : false,
        ]);
    }

    public function save(Request $request)
    {
        $admin = $this->guard($request);

        $request->validate([
            'otp_provider' => 'required|in:' . implode(',', LoginSettings::PROVIDERS),
            'google_phone' => 'sometimes|in:required,optional',
            'login_design' => 'sometimes|in:' . implode(',', LoginSettings::DESIGNS),
        ]);

        $new = [];
        foreach (LoginSettings::BOOLS as $k) {
            $new[$k] = $request->boolean($k);
        }

        if (! LoginSettings::anyMethodEnabled($new)) {
            return back()->withInput()->withErrors(['methods' => __('loginsettings.need_one')]);
        }

        if (! $new['otp_enabled'] && (empty($admin->password) || ! $new['password_for_staff'])) {
            return back()->withInput()->withErrors(['methods' => __('loginpw.need_admin_pw')]);
        }

        $new['otp_provider'] = (string) $request->input('otp_provider');
        // Older forms may not send it; keep the current mode then.
        if ($request->filled('google_phone')) {
            $new['google_phone'] = (string) $request->input('google_phone');
        }
        if ($request->filled('login_design')) {
            $new['login_design'] = (string) $request->input('login_design');
        }
        $new['firebase_api_key'] = trim((string) $request->input('firebase_api_key', ''));
        $new['firebase_auth_domain'] = trim((string) $request->input('firebase_auth_domain', ''));
        $new['firebase_project_id'] = trim((string) $request->input('firebase_project_id', ''));
        $fbMissing = $new['firebase_api_key'] === '' || $new['firebase_auth_domain'] === '' || $new['firebase_project_id'] === '';
        if ($new['otp_provider'] === 'firebase' && $fbMissing) {
            return back()->withInput()->withErrors(['methods' => __('loginsettings.fbl_need_config')]);
        }
        if ($new['google_enabled'] && $fbMissing) {
            return back()->withInput()->withErrors(['methods' => __('loginsettings.gg_need_config')]);
        }

        LoginSettings::save($new);
        app(AuditService::class)->log($admin, 'login_settings.save', 'setting', 'login', $new);

        return back()->with('status', __('loginsettings.saved'));
    }
}