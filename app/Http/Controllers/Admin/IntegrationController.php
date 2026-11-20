<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\{DigestSettings, PushSettings, ShareSettings};
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        return view('admin.integrations', [
            'push'   => PushSettings::all(),
            'pushSa' => PushSettings::hasServiceAccount(),
            'share'  => ShareSettings::all(),
            'shareTok' => ShareSettings::token() !== '',
            'digest' => DigestSettings::all(),
        ]);
    }

    public function savePush(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate([
            'api_key'    => ['nullable', 'string', 'max:200'],
            'project_id' => ['nullable', 'string', 'max:100'],
            'app_id'     => ['nullable', 'string', 'max:200'],
            'sender_id'  => ['nullable', 'string', 'max:50'],
            'vapid_key'  => ['nullable', 'string', 'max:200'],
            'sa_json'    => ['nullable', 'string', 'max:8000', 'json'],
        ]);

        PushSettings::save($request->only(['on', 'api_key', 'project_id', 'app_id', 'sender_id', 'vapid_key', 'sa_json']));

        return back()->with('status', __('issues.saved_ok'));
    }

    public function saveShare(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate([
            'fb_page_id' => ['nullable', 'string', 'max:100'],
            'fb_token'   => ['nullable', 'string', 'max:500'],
        ]);

        ShareSettings::save($request->only(['on', 'fb_page_id', 'fb_token']));

        return back()->with('status', __('issues.saved_ok'));
    }

    public function saveDigest(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate(['emails' => ['nullable', 'string', 'max:2000']]);
        DigestSettings::save($request->boolean('on'), (string) $request->input('emails', ''));

        return back()->with('status', __('issues.saved_ok'));
    }
}
