<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\FooterLinks;
use Illuminate\Http\Request;

class FooterLinksController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        return view('admin.footer-links', [
            'builtin' => FooterLinks::builtin(),
            'custom'  => FooterLinks::custom(),
        ]);
    }

    public function save(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        FooterLinks::saveBuiltin($request->input('links', []));

        return back()->with('status', __('issues.saved_ok'));
    }

    public function add(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'url'   => ['required', 'string', 'max:300', 'regex:#^(https?://|/)#i'],
        ]);

        FooterLinks::addCustom($request->input('label'), $request->input('url'));

        return back()->with('status', __('issues.saved_ok'));
    }

    public function delete(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate(['index' => ['required', 'integer', 'min:0']]);
        FooterLinks::deleteCustom((int) $request->input('index'));

        return back()->with('status', __('issues.deleted_ok'));
    }
}
