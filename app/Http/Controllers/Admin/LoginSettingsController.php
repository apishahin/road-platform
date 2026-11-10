<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return view('admin.loginsettings.index', ['s' => LoginSettings::all()]);
    }

    public function save(Request $request)
    {
        $admin = $this->guard($request);

        $request->validate([
            'otp_provider' => 'required|in:' . implode(',', LoginSettings::PROVIDERS),
        ]);

        $new = [];
        foreach (LoginSettings::BOOLS as $k) {
            $new[$k] = $request->boolean($k);
        }
        // Google login is not built yet: keep it off.
        $new['google_enabled'] = false;

        if (! LoginSettings::anyMethodEnabled($new)) {
            return back()->withInput()->withErrors(['methods' => __('loginsettings.need_one')]);
        }

        $new['otp_provider'] = (string) $request->input('otp_provider');
        LoginSettings::save($new);
        app(AuditService::class)->log($admin, 'login_settings.save', 'setting', 'login', $new);

        return back()->with('status', __('loginsettings.saved'));
    }
}