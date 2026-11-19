<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdSettings;
use Illuminate\Http\Request;

/** Admin → Ads: AdSense publisher id, per-slot ad tags, ads.txt. */
class AdSettingsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        return view('admin.ads', ['ads' => AdSettings::all(), 'slots' => AdSettings::SLOTS]);
    }

    public function save(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $data = $request->validate([
            'on'             => 'nullable|boolean',
            'hide_for_staff' => 'nullable|boolean',
            'publisher_id'   => ['nullable', 'string', 'max:40', 'regex:/^ca-pub-\d{10,20}$/'],
            'ads_txt'        => 'nullable|string|max:10000',
            'slots'          => 'nullable|array',
            'slots.*'        => 'nullable|string|max:8000',
        ], [
            'publisher_id.regex' => __('issues.ad_pub_bad'),
        ]);

        AdSettings::save($data);

        return back()->with('status', __('issues.ad_saved'));
    }
}
