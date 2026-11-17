<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\AppearanceSettings;
use App\Support\SiteSections;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('admin.site', [
            's'   => SiteSections::all(),
            // merged appearance panel (one page for everything visual)
            'ap'  => AppearanceSettings::all(),
            'apd' => AppearanceSettings::defaults(),
        ]);
    }

    /** Move an uploaded hero slide image to public/uploads/hero with a safe generated name. */
    private function storeSlideImage($file): string
    {
        $info  = @getimagesize($file->getRealPath());
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

        if ($info === false || ! isset($types[$info[2]])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'hero_slides' => __('issues.site_slide_img_bad'),
            ]);
        }

        $dir = public_path(SiteSections::HERO_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'hero-' . Str::lower(Str::random(16)) . '.' . $types[$info[2]];
        $file->move($dir, $name);

        return $name;
    }

    /** Move an uploaded popup image to public/uploads/popup with a safe generated name. */
    private function storePopupImage($file): string
    {
        $info  = @getimagesize($file->getRealPath());
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

        if ($info === false || ! isset($types[$info[2]])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'popup_file' => __('issues.popup_img_bad'),
            ]);
        }

        $dir = public_path(SiteSections::POPUP_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'popup-' . Str::lower(Str::random(16)) . '.' . $types[$info[2]];
        $file->move($dir, $name);

        return $name;
    }

    public function save(Request $request, AuditService $audit)
    {
        $this->guard($request);

        $old = SiteSections::all();

        $rules = [];
        foreach (SiteSections::SECTIONS as $sec) {
            foreach (SiteSections::TEXT_FIELDS[$sec] as $f) {
                $rules["sec_{$sec}_{$f}"] = ['nullable', 'string', 'max:500'];
            }
            foreach (SiteSections::BOOL_FIELDS[$sec] ?? [] as $b) {
                $rules["sec_{$sec}_{$b}"] = ['nullable', 'boolean'];
            }
            if (in_array($sec, SiteSections::SIZE_FIELDS, true)) {
                $rules["sec_{$sec}_size"] = ['nullable', 'in:normal,large,xlarge'];
            }
        }
        $rules['footer_text'] = ['nullable', 'string', 'max:1000'];
        foreach (SiteSections::SOCIALS as $soc) {
            $rules['footer_' . $soc] = ['nullable', 'string', 'max:300'];
        }
        $rules['design_home']   = ['nullable', 'in:' . implode(',', SiteSections::DESIGNS)];
        $rules['design_report'] = ['nullable', 'in:' . implode(',', SiteSections::DESIGNS)];
        foreach (SiteSections::THEME_COLORS as $c) {
            $rules['theme_' . $c] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        foreach (SiteSections::FLOAT_BTNS as $b) {
            $rules["float_{$b}_label"]  = ['nullable', 'string', 'max:60'];
            $rules["float_{$b}_color"]  = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules["float_{$b}_size"]   = ['nullable', 'in:' . implode(',', SiteSections::FLOAT_SIZES)];
            $rules["float_{$b}_pos"]    = ['nullable', 'in:' . implode(',', SiteSections::FLOAT_POS)];
            $rules["float_{$b}_bottom"] = ['nullable', 'integer', 'min:0', 'max:400'];
            $rules["float_{$b}_side"]   = ['nullable', 'integer', 'min:0', 'max:400'];
        }
        $rules['popup_text']    = ['nullable', 'string', 'max:300'];
        $rules['popup_link']    = ['nullable', 'string', 'max:300'];
        $rules['popup_img_url'] = ['nullable', 'string', 'max:300'];
        $rules['popup_delay']   = ['nullable', 'integer', 'min:0', 'max:120'];
        $rules['popup_freq']    = ['nullable', 'in:' . implode(',', SiteSections::POPUP_FREQS)];
        $rules['popup_file']    = ['nullable', 'file', 'max:4096'];

        for ($i = 0; $i < SiteSections::HERO_SLIDES; $i++) {
            $rules["slide_file_{$i}"]  = ['nullable', 'file', 'max:4096'];
            $rules["slide_url_{$i}"]   = ['nullable', 'string', 'max:300'];
            $rules["slide_title_{$i}"] = ['nullable', 'string', 'max:300'];
            $rules["slide_text_{$i}"]  = ['nullable', 'string', 'max:500'];
            $rules["slide_tag_{$i}"]   = ['nullable', 'string', 'max:100'];
        }
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
            foreach (SiteSections::BOOL_FIELDS[$sec] ?? [] as $b) {
                $row[$b] = $request->boolean("sec_{$sec}_{$b}");
            }
            $new['sections'][$sec] = $row;
        }

        foreach (SiteSections::NAV_ITEMS as $n) {
            $new['nav'][$n] = $request->boolean("nav_{$n}");
        }

        $new['footer'] = [
            'text'     => $data['footer_text'] ?? '',
            'links_on' => $request->boolean('footer_links_on'),
            'dark'     => $request->boolean('footer_dark'),
        ];
        foreach (SiteSections::SOCIALS as $soc) {
            $new['footer'][$soc] = $data['footer_' . $soc] ?? '';
        }

        $new['design'] = [
            'home'   => $data['design_home'] ?? 'classic',
            'report' => $data['design_report'] ?? 'classic',
        ];

        $new['theme'] = ['on' => $request->boolean('theme_on')];
        foreach (SiteSections::THEME_COLORS as $c) {
            $new['theme'][$c] = $data['theme_' . $c] ?? $old['theme'][$c];
        }

        // Floating buttons (install pill / report FAB / scroll-to-top).
        $new['float'] = [];
        foreach (SiteSections::FLOAT_BTNS as $b) {
            $new['float'][$b . '_on'] = $request->boolean("float_{$b}_on");
            if ($b !== 'top') {
                $new['float'][$b . '_label'] = $data["float_{$b}_label"] ?? '';
            }
            $new['float'][$b . '_color']  = $data["float_{$b}_color"] ?? $old['float'][$b . '_color'];
            $new['float'][$b . '_size']   = $data["float_{$b}_size"] ?? 'md';
            $new['float'][$b . '_pos']    = $data["float_{$b}_pos"] ?? $old['float'][$b . '_pos'];
            $new['float'][$b . '_bottom'] = $data["float_{$b}_bottom"] ?? $old['float'][$b . '_bottom'];
            $new['float'][$b . '_side']   = $data["float_{$b}_side"] ?? $old['float'][$b . '_side'];
        }

        // Advertisement popup: keep old uploaded image unless replaced or cleared.
        $ppImg = $old['popup']['img'] ?? '';
        if ($request->boolean('popup_img_remove')) {
            $ppImg = '';
        }
        if ($request->hasFile('popup_file')) {
            $ppImg = $this->storePopupImage($request->file('popup_file'));
        }
        $new['popup'] = [
            'on'      => $request->boolean('popup_on'),
            'text'    => $data['popup_text'] ?? '',
            'link'    => $data['popup_link'] ?? '',
            'img'     => $ppImg,
            'img_url' => $data['popup_img_url'] ?? '',
            'delay'   => $data['popup_delay'] ?? $old['popup']['delay'],
            'freq'    => $data['popup_freq'] ?? $old['popup']['freq'],
        ];

        // Hero slider: keep old uploaded image unless a new file replaces it or admin clears it.
        $slides = [];
        $oldSlides = $old['hero_slider']['slides'] ?? [];
        for ($i = 0; $i < SiteSections::HERO_SLIDES; $i++) {
            $img = $oldSlides[$i]['img'] ?? '';
            if ($request->boolean("slide_remove_{$i}")) {
                $img = '';
            }
            if ($request->hasFile("slide_file_{$i}")) {
                $img = $this->storeSlideImage($request->file("slide_file_{$i}"));
            }
            $slides[] = [
                'img'   => $img,
                'url'   => $data["slide_url_{$i}"] ?? '',
                'title' => $data["slide_title_{$i}"] ?? '',
                'text'  => $data["slide_text_{$i}"] ?? '',
                'tag'   => $data["slide_tag_{$i}"] ?? '',
            ];
        }
        $new['hero_slider'] = ['on' => $request->boolean('hero_slider_on'), 'slides' => $slides];

        SiteSections::save($new);
        $audit->log($request->user(), 'site.saved', 'settings', SiteSections::KEY, [
            'sections_off' => array_keys(array_filter($new['sections'], fn ($x) => ! $x['on'])),
        ]);

        return redirect()->route('admin.site.index')->with('status', __('issues.fund_saved'));
    }
}
