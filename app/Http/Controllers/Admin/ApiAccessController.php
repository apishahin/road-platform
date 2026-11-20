<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\ApiKeySettings;
use Illuminate\Http\Request;

class ApiAccessController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function index()
    {
        $this->guard();
        return view('admin.api-access', ['cfg' => ApiKeySettings::all()]);
    }

    public function toggle(Request $request)
    {
        $this->guard();
        ApiKeySettings::savePublicRead($request->boolean('public_read'));
        app(\App\Services\AuditService::class)->log(
            $request->user(), 'api.public_read', 'settings', 'api_access',
            ['public_read' => $request->boolean('public_read')]
        );

        return back()->with('status', __('issues.api_saved'));
    }

    public function createKey(Request $request, AuditService $audit)
    {
        $this->guard();
        $data = $request->validate(['label' => ['required', 'string', 'max:80']]);

        [$row, $plain] = ApiKeySettings::createKey($data['label']);
        $audit->log($request->user(), 'api.key_created', 'settings', 'api_access', [
            'key_id' => $row['id'], 'label' => $row['label'],
        ]);

        // The plaintext key is shown exactly once via this flash.
        return back()->with('status', __('issues.api_key_created'))
            ->with('new_api_key', $plain);
    }

    public function revoke(Request $request, string $id, AuditService $audit)
    {
        $this->guard();
        ApiKeySettings::revoke($id);
        $audit->log($request->user(), 'api.key_revoked', 'settings', 'api_access', ['key_id' => $id]);

        return back()->with('status', __('issues.api_key_revoked'));
    }
}
