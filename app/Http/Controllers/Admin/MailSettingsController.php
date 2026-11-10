<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailSettingsController extends Controller
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
        $s = MailSettings::all();
        unset($s['password']);

        return view('admin.mail.index', [
            's' => $s,
            'hasPassword' => MailSettings::hasPassword(),
            'current' => (string) config('mail.default'),
        ]);
    }

    public function save(Request $request)
    {
        $admin = $this->guard($request);
        $mode = (string) $request->input('mode');

        $rules = [
            'mode' => 'required|in:log,smtp,gmail',
            'from_name' => 'nullable|string|max:100',
            'from_address' => 'nullable|email|max:150',
            'password' => 'nullable|string|max:200',
        ];
        if ($mode === 'smtp') {
            $rules['host'] = 'required|string|max:150';
            $rules['port'] = 'required|integer|between:1,65535';
            $rules['encryption'] = 'required|in:tls,ssl,none';
            $rules['username'] = 'nullable|string|max:150';
            $rules['from_address'] = 'required|email|max:150';
        }
        if ($mode === 'gmail') {
            $rules['username'] = 'required|email|max:150';
        }
        $data = $request->validate($rules);

        $prev = MailSettings::all()['mode'];
        $changed = $prev !== $mode;
        $plain = trim((string) $request->input('password', ''));
        if ($mode === 'gmail') {
            $plain = str_replace(' ', '', $plain);
        }

        if ($mode === 'gmail' && $plain === '' && ($changed || ! MailSettings::hasPassword())) {
            return back()->withInput($request->except('password'))
                ->withErrors(['password' => __('mailsettings.need_password')]);
        }

        $new = [
            'mode' => $mode,
            'from_name' => (string) ($data['from_name'] ?? ''),
            'from_address' => (string) ($data['from_address'] ?? ''),
        ];
        if ($mode === 'smtp') {
            $new['host'] = $data['host'];
            $new['port'] = (int) $data['port'];
            $new['encryption'] = $data['encryption'];
            $new['username'] = (string) ($data['username'] ?? '');
        }
        if ($mode === 'gmail') {
            $new['username'] = $data['username'];
        }

        MailSettings::save($new, $plain, $changed && $plain === '');
        app(AuditService::class)->log($admin, 'mail_settings.save', 'setting', 'mail', [
            'mode' => $mode,
            'password_changed' => $plain !== '',
        ]);

        return back()->with('status', __('mailsettings.saved'));
    }

    public function test(Request $request)
    {
        $admin = $this->guard($request);
        $data = $request->validate(['to' => 'required|email|max:150']);

        try {
            Mail::raw(__('mailsettings.test_body'), function ($m) use ($data) {
                $m->to($data['to'])->subject(__('mailsettings.test_subject'));
            });
        } catch (\Throwable $e) {
            app(AuditService::class)->log($admin, 'mail_settings.test', 'setting', 'mail', ['result' => 'failed']);

            return back()->withInput()->with('mail_error', mb_substr($e->getMessage(), 0, 300));
        }

        app(AuditService::class)->log($admin, 'mail_settings.test', 'setting', 'mail', ['result' => 'ok']);
        $msg = config('mail.default') === 'log' ? __('mailsettings.test_logged') : __('mailsettings.test_ok');

        return back()->with('status', $msg);
    }
}