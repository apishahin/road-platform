@extends('layouts.app')

@section('title', __('issues.site_title'))

@section('content')
@php
    use App\Support\SiteSections;
    $inp  = 'width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;';
    $lbl  = 'display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:4px;';
    $btn  = 'display:inline-block;padding:9px 18px;border-radius:8px;background:#0f172a;color:#fff;font-weight:700;font-size:14px;border:0;cursor:pointer;';
    $card = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
    $chk  = 'display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#334155;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;background:#f8fafc;';
    $secs = $s['sections'];
    $nav  = $s['nav'];
    $footer = $s['footer'];
    $hs   = $s['hero_slider'];
@endphp

<div class="mx-auto max-w-3xl px-4 py-6">
    <h1 style="font-size:24px;font-weight:800;color:#0f172a;">{{ __('issues.site_title') }}</h1>
    <p style="margin-top:2px;font-size:14px;color:#64748b;">{{ __('issues.site_sub') }}</p>

    @if (session('status'))
        <div style="margin-top:14px;padding:11px 14px;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-weight:600;font-size:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div style="margin-top:14px;padding:11px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:14px;">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- ============ Appearance (merged here — everything visual on ONE page) ============ --}}
    <h2 id="appearance" style="margin-top:20px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_appearance_merged') }}</h2>
    <p style="margin-top:2px;font-size:12px;color:#94a3b8;">{{ __('issues.site_appearance_merged_hint') }}</p>
    <div style="{{ $card }};margin-top:10px;">
        @include('admin._appearance-form')
    </div>

    <form method="POST" action="{{ route('admin.site.save') }}" enctype="multipart/form-data">
        @csrf

        {{-- ============ Design variants ============ --}}
        <h2 style="margin-top:20px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_design') }}</h2>
        <div style="{{ $card }};margin-top:10px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
            <div>
                <label style="{{ $lbl }}">{{ __('issues.site_design_home') }}</label>
                <select name="design_home" style="{{ $inp }}">
                    @foreach (SiteSections::DESIGNS as $dsg)
                        <option value="{{ $dsg }}" @selected(($s['design']['home'] ?? 'classic') === $dsg)>{{ __('issues.site_design_' . $dsg) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="{{ $lbl }}">{{ __('issues.site_design_report') }}</label>
                <select name="design_report" style="{{ $inp }}">
                    @foreach (SiteSections::DESIGNS as $dsg)
                        <option value="{{ $dsg }}" @selected(($s['design']['report'] ?? 'classic') === $dsg)>{{ __('issues.site_design_' . $dsg) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p style="margin-top:6px;font-size:12px;color:#94a3b8;">{{ __('issues.site_design_hint') }}</p>

        {{-- ============ Colour & gradient theme ============ --}}
        @php $th = $s['theme'] ?? []; @endphp
        <h2 style="margin-top:24px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_sec_theme') }}</h2>
        <div style="{{ $card }};margin-top:10px;" x-data="{ on: {{ ! empty($th['on']) ? 'true' : 'false' }} }">
            <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:10px;">
                <div style="max-width:60ch;">
                    <div style="font-weight:800;font-size:14px;color:#0f172a;">{{ __('issues.site_sec_theme') }}</div>
                    <p style="font-size:12px;color:#64748b;margin-top:2px;">{{ __('issues.site_sec_theme_hint') }}</p>
                </div>
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#334155;cursor:pointer;">
                    <input type="checkbox" name="theme_on" value="1" x-model="on" @checked(! empty($th['on']))>
                    {{ __('issues.site_f_theme_on') }}
                </label>
            </div>
            <p style="font-size:12px;color:#94a3b8;margin-top:8px;">{{ __('issues.site_f_theme_note') }}</p>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-top:10px;" x-show="on" x-cloak>
                @foreach ([
                    'green'      => 'site_f_theme_c_green',
                    'green_dark' => 'site_f_theme_c_green_dark',
                    'green_deep' => 'site_f_theme_c_green_deep',
                    'accent'     => 'site_f_theme_c_accent',
                    'cta_from'   => 'site_f_theme_c_cta_from',
                    'cta_to'     => 'site_f_theme_c_cta_to',
                ] as $ck => $lk)
                    <div>
                        <label style="{{ $lbl }}">{{ __('issues.' . $lk) }}</label>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="text" name="theme_{{ $ck }}" value="{{ old('theme_' . $ck, $th[$ck] ?? '') }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" placeholder="#078a48" style="{{ $inp }};max-width:110px;font-family:monospace;">
                            <span style="display:inline-block;width:30px;height:30px;border-radius:8px;border:1px solid #e2e8f0;background:{{ $th[$ck] ?? '#fff' }};" aria-hidden="true"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ============ Hero slider ============ --}}
        <h2 style="margin-top:24px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_hero_slider') }}</h2>
        <div style="{{ $card }};margin-top:10px;" x-data="{ on: {{ $hs['on'] ? 'true' : 'false' }} }">
            <label style="font-size:15px;font-weight:800;color:#0f172a;cursor:pointer;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="hero_slider_on" value="1" x-model="on" @checked($hs['on'])> {{ __('issues.site_hero_slider_on') }}
            </label>
            <p style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ __('issues.site_hero_slider_hint') }}</p>

            <div x-show="on" x-cloak style="margin-top:12px;display:flex;flex-direction:column;gap:14px;">
                @for ($i = 0; $i < SiteSections::HERO_SLIDES; $i++)
                    @php $sl = $hs['slides'][$i]; @endphp
                    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#f8fafc;">
                        <p style="font-size:13px;font-weight:800;color:#0f172a;margin-bottom:8px;">{{ __('issues.site_slide') }} {{ \App\Support\IssuePresenter::num($i + 1) }}</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div>
                                <label style="{{ $lbl }}">{{ __('issues.site_slide_img') }}</label>
                                @if ($sl['img'] !== '')
                                    <img src="{{ SiteSections::heroImgUrl($sl['img']) }}" alt="" style="height:44px;width:auto;border-radius:8px;border:1px solid #e2e8f0;background:#fff;padding:2px;margin-bottom:6px;">
                                    <label style="display:block;font-size:12px;font-weight:600;color:#b91c1c;margin-bottom:4px;"><input type="checkbox" name="slide_remove_{{ $i }}" value="1"> {{ __('issues.site_slide_img_remove') }}</label>
                                @endif
                                <input type="file" name="slide_file_{{ $i }}" accept=".png,.jpg,.jpeg,.webp" style="font-size:13px;">
                            </div>
                            <div>
                                <label style="{{ $lbl }}">{{ __('issues.site_slide_url') }}</label>
                                <input name="slide_url_{{ $i }}" value="{{ old('slide_url_' . $i, $sl['url']) }}" maxlength="300" placeholder="https://…" style="{{ $inp }}">
                            </div>
                            <div>
                                <label style="{{ $lbl }}">{{ __('issues.site_slide_title') }}</label>
                                <input name="slide_title_{{ $i }}" value="{{ old('slide_title_' . $i, $sl['title']) }}" maxlength="300" placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">
                            </div>
                            <div>
                                <label style="{{ $lbl }}">{{ __('issues.site_slide_tag') }}</label>
                                <input name="slide_tag_{{ $i }}" value="{{ old('slide_tag_' . $i, $sl['tag']) }}" maxlength="100" placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">
                            </div>
                            <div style="grid-column:1 / -1;">
                                <label style="{{ $lbl }}">{{ __('issues.site_slide_text') }}</label>
                                <input name="slide_text_{{ $i }}" value="{{ old('slide_text_' . $i, $sl['text']) }}" maxlength="500" placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">
                            </div>
                        </div>
                    </div>
                @endfor
                <p style="font-size:12px;color:#94a3b8;">{{ __('issues.site_hero_slider_note') }}</p>
            </div>
        </div>

        {{-- ============ Homepage sections ============ --}}
        <h2 style="margin-top:24px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_sections') }}</h2>
        <p style="margin-top:2px;font-size:13px;color:#64748b;">{{ __('issues.site_sections_hint') }}</p>

        <div style="margin-top:10px;display:flex;flex-direction:column;gap:12px;">
            @foreach (SiteSections::SECTIONS as $sec)
                @php $row = $secs[$sec]; @endphp
                <div style="{{ $card }}" x-data="{ on: {{ $row['on'] ? 'true' : 'false' }} }">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <div>
                            <label style="font-size:15px;font-weight:800;color:#0f172a;cursor:pointer;display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="sec_{{ $sec }}_on" value="1" x-model="on" @checked($row['on'])> {{ __('issues.site_sec_' . $sec) }}
                            </label>
                            <p style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ __('issues.site_sec_' . $sec . '_hint') }}</p>
                        </div>
                        @if (in_array($sec, SiteSections::SIZE_FIELDS, true))
                            <div>
                                <label style="{{ $lbl }};margin-bottom:2px;">{{ __('issues.site_size') }}</label>
                                <select name="sec_{{ $sec }}_size" style="{{ $inp }};width:auto;padding:6px 10px;font-size:13px;">
                                    @foreach (SiteSections::SIZES as $sz)
                                        <option value="{{ $sz }}" @selected(($row['size'] ?? 'normal') === $sz)>{{ __('issues.site_size_' . $sz) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    @if (SiteSections::TEXT_FIELDS[$sec] || (SiteSections::BOOL_FIELDS[$sec] ?? []))
                        <div x-show="on" x-cloak style="margin-top:12px;display:grid;grid-template-columns:1fr;gap:10px;">
                            @foreach (SiteSections::TEXT_FIELDS[$sec] as $f)
                                <div>
                                    <label style="{{ $lbl }}">{{ __('issues.site_f_' . $sec . '_' . $f) }}</label>
                                    <input name="sec_{{ $sec }}_{{ $f }}" value="{{ old('sec_' . $sec . '_' . $f, $row[$f] ?? '') }}" maxlength="500"
                                           placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">
                                </div>
                            @endforeach

                            @foreach (SiteSections::BOOL_FIELDS[$sec] ?? [] as $b)
                                <label style="{{ $chk }}">
                                    <input type="checkbox" name="sec_{{ $sec }}_{{ $b }}" value="1" @checked($row[$b] ?? true)> {{ __('issues.site_f_' . $sec . '_' . $b) }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ============ Navbar ============ --}}
        <h2 style="margin-top:24px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_nav') }}</h2>
        <div style="{{ $card }};margin-top:10px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                @foreach (SiteSections::NAV_ITEMS as $n)
                    <label style="{{ $chk }}">
                        <input type="checkbox" name="nav_{{ $n }}" value="1" @checked($nav[$n])> {{ __('issues.site_nav_' . $n) }}
                    </label>
                @endforeach
            </div>
            <p style="margin-top:8px;font-size:12px;color:#94a3b8;">{{ __('issues.site_nav_hint') }}</p>
        </div>

        {{-- ============ Footer ============ --}}
        <h2 style="margin-top:24px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_footer') }}</h2>
        <div style="{{ $card }};margin-top:10px;display:flex;flex-direction:column;gap:10px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#334155;">
                <input type="checkbox" name="footer_links_on" value="1" @checked($footer['links_on'])> {{ __('issues.site_footer_links') }}
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#334155;">
                <input type="checkbox" name="footer_dark" value="1" @checked($footer['dark'])> {{ __('issues.site_footer_dark') }}
            </label>
            <div>
                <label style="{{ $lbl }}">{{ __('issues.site_footer_text') }}</label>
                <textarea name="footer_text" rows="3" maxlength="1000" placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">{{ old('footer_text', $footer['text']) }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                @foreach (SiteSections::SOCIALS as $soc)
                    <div>
                        <label style="{{ $lbl }}">{{ __('issues.site_footer_' . $soc) }}</label>
                        <input name="footer_{{ $soc }}" value="{{ old('footer_' . $soc, $footer[$soc] ?? '') }}" maxlength="300" placeholder="https://…" style="{{ $inp }}">
                    </div>
                @endforeach
            </div>
            <p style="font-size:12px;color:#94a3b8;">{{ __('issues.site_footer_social_hint') }}</p>
        </div>

        <button type="submit" style="{{ $btn }};margin-top:18px;">{{ __('issues.fund_save') }}</button>
    </form>
</div>
@endsection
