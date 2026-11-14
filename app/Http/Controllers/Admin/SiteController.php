<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\SiteSections;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('admin.site', ['s' => SiteSections::all()]);
    }

    public function save(Request $request, AuditService $audit)
    {
        $this->guard($request);

        $rules = [];
        foreach (SiteSections::SECTIONS as $sec) {
            foreach (SiteSections::TEXT_FIELDS[$sec] as $f) {
                $rules["sec_{$sec}_{$f}"] = ['nullable', 'string', 'max:500'];
            }
            if (in_array($sec, SiteSections::SIZE_FIELDS, true)) {
                $rules["sec_{$sec}_size"] = ['nullable', 'in:normal,large,xlarge'];
            }
        }
        $rules['footer_text'] = ['nullable', 'string', 'max:1000'];
        $data = $request->validate($rules);

        $new = ['sections' => [], 'nav' => [], 'footer' => []];

        foreach (SiteSections::SECTIONS as $sec) {
            $row = ['on' => $request->boolean("sec_{$sec}_on")];
            if (in_array($sec, SiteSections::SIZE_FIELDS, true)) {
                $row['size'] = $data["sec_{$sec}_size"] ?? 'normal';
            }
            foreach (SiteSections::TEXT_FIELDS[$sec] as $f) {
                $row[$f] = $data["sec_{$sec}_{$f}"] ?? '';
            }
            $new['sections'][$sec] = $row;
        }

        foreach (SiteSections::NAV_ITEMS as $n) {
            $new['nav'][$n] = $request->boolean("nav_{$n}");
        }

        $new['footer'] = [
            'text'     => $data['footer_text'] ?? '',
            'links_on' => $request->boolean('footer_links_on'),
        ];

        SiteSections::save($new);
        $audit->log($request->user(), 'site.saved', 'settings', SiteSections::KEY, [
            'sections_off' => array_keys(array_filter($new['sections'], fn ($x) => ! $x['on'])),
        ]);

        return redirect()->route('admin.site.index')->with('status', __('issues.fund_saved'));
    }
}
