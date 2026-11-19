<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SponsorSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SponsorController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        return view('admin.sponsors', ['data' => SponsorSettings::section()]);
    }

    public function save(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate(['title' => ['nullable', 'string', 'max:80']]);
        SponsorSettings::saveSection($request->boolean('on'), $request->input('title'));

        return back()->with('status', __('issues.saved_ok'));
    }

    public function add(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'url'  => ['nullable', 'string', 'max:300', 'regex:#^https?://#i'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $dir = public_path(SponsorSettings::DIR);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $ext  = strtolower($request->file('logo')->getClientOriginalExtension() ?: 'png');
            $file = Str::random(16) . '.' . $ext;
            $request->file('logo')->move($dir, $file);
            $logoPath = '/' . SponsorSettings::DIR . '/' . $file;
        }

        SponsorSettings::add($request->input('name'), $request->input('url'), $logoPath);

        return back()->with('status', __('issues.saved_ok'));
    }

    public function delete(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate(['index' => ['required', 'integer', 'min:0']]);
        $logo = SponsorSettings::delete((int) $request->input('index'));

        if ($logo) {
            $abs = public_path(ltrim($logo, '/'));
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        return back()->with('status', __('issues.deleted_ok'));
    }
}
