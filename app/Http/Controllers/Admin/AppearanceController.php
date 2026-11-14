<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\AppearanceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AppearanceController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function index()
    {
        $this->guard();

        // Appearance editing now lives inside "Homepage & site" (one page for everything visual).
        return redirect(route('admin.site.index') . '#appearance');
    }

    public function update(Request $request, AuditService $audit)
    {
        $this->guard();

        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $data = $request->validate([
            'font'        => ['required', Rule::in(AppearanceSettings::FONTS)],
            'size'        => ['required', Rule::in(array_keys(AppearanceSettings::SIZES))],
            'brand'       => $hex,
            'header_bg'   => $hex,
            'header_text' => $hex,
            'name_bn'     => ['nullable', 'string', 'max:60'],
            'name_en'     => ['nullable', 'string', 'max:60'],
            'tagline_bn'  => ['nullable', 'string', 'max:120'],
            'tagline_en'  => ['nullable', 'string', 'max:120'],
            'font_file'   => ['nullable', 'file', 'max:2048'],
            'logo'        => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $old = AppearanceSettings::all();
        $new = $old;

        $new['font']        = $data['font'];
        $new['size']        = $data['size'];
        $new['brand']       = strtolower($data['brand']);
        $new['header_bg']   = strtolower($data['header_bg']);
        $new['header_text'] = strtolower($data['header_text']);
        $new['name_bn']     = trim((string) ($data['name_bn'] ?? ''));
        $new['name_en']     = trim((string) ($data['name_en'] ?? ''));
        $new['tagline_bn']  = trim((string) ($data['tagline_bn'] ?? ''));
        $new['tagline_en']  = trim((string) ($data['tagline_en'] ?? ''));

        $new['issue_view'] = $request->input('issue_view') === 'detailed' ? 'detailed' : 'simple';

        $dir = public_path(AppearanceSettings::DIR);
        $filesToDelete = [];

        // ── Font upload ─────────────────────────────────────────
        if ($request->hasFile('font_file')) {
            $file = $request->file('font_file');
            $ext  = strtolower($file->getClientOriginalExtension());

            $truetype = ["\x00\x01\x00\x00", 'OTTO', 'true'];
            $magic = ['woff2' => ['wOF2'], 'woff' => ['wOFF'], 'ttf' => $truetype, 'otf' => $truetype];

            if (! isset($magic[$ext])) {
                return back()->withInput()->withErrors(['font_file' => 'ফন্ট ফাইল হতে হবে woff2, woff, ttf বা otf।']);
            }
            $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 4);
            if (! in_array($head, $magic[$ext], true)) {
                return back()->withInput()->withErrors(['font_file' => 'ফাইলটি আসল ফন্ট ফাইল বলে মনে হচ্ছে না।']);
            }

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = 'font-' . Str::lower(Str::random(16)) . '.' . $ext;
            $file->move($dir, $name);

            $filesToDelete[]   = $old['font_file'];
            $new['font_file']  = $name;
            $new['font']       = 'custom';
        }

        if ($new['font'] === 'custom' && empty($new['font_file'])) {
            return back()->withInput()->withErrors(['font' => 'নিজের ফন্ট বেছে নিলে আগে একটি ফন্ট ফাইল আপলোড করুন।']);
        }

        // ── Logo upload / removal ───────────────────────────────
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $info = @getimagesize($file->getRealPath());
            $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

            if ($info === false || ! isset($types[$info[2]])) {
                return back()->withInput()->withErrors(['logo' => 'লোগো হতে হবে PNG, JPG বা WebP ছবি।']);
            }

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = 'logo-' . Str::lower(Str::random(16)) . '.' . $types[$info[2]];
            $file->move($dir, $name);

            $filesToDelete[] = $old['logo'];
            $new['logo']     = $name;
        } elseif ($request->boolean('remove_logo')) {
            $filesToDelete[] = $old['logo'];
            $new['logo']     = null;
        }

        $saved = AppearanceSettings::save($new);

        foreach ($filesToDelete as $f) {
            if ($f !== null && $f !== $saved['font_file'] && $f !== $saved['logo']) {
                AppearanceSettings::removeFile($f);
            }
        }

        // ── Audit log (what changed) ────────────────────────────
        $changes = [];
        foreach ($saved as $k => $v) {
            if (($old[$k] ?? null) !== $v) {
                $changes[$k] = ['from' => $old[$k] ?? null, 'to' => $v];
            }
        }
        $audit->log($request->user(), 'appearance.changed', 'setting', 'appearance', ['changes' => $changes]);

        // ── Readability warnings (saved anyway) ─────────────────
        $warnings = [];
        if (AppearanceSettings::contrast($saved['header_bg'], $saved['header_text']) < 4.5) {
            $warnings[] = 'হেডারের লেখা আর পটভূমির রং খুব কাছাকাছি, লেখা পড়তে কষ্ট হতে পারে।';
        }
        if (AppearanceSettings::contrast($saved['brand'], '#ffffff') < 3.0) {
            $warnings[] = 'মূল রংটি এত হালকা যে বোতামের সাদা লেখা পড়া কঠিন হবে।';
        }

        $redirect = redirect()->route('admin.appearance.index')->with('status', 'চেহারা সংরক্ষণ করা হয়েছে।');
        if ($warnings) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    public function reset(Request $request, AuditService $audit)
    {
        $this->guard();

        $before = AppearanceSettings::all();
        AppearanceSettings::reset();
        $audit->log($request->user(), 'appearance.reset', 'setting', 'appearance', ['from' => $before]);

        return redirect()->route('admin.appearance.index')->with('status', 'ডিফল্ট চেহারায় ফেরানো হয়েছে।');
    }
}