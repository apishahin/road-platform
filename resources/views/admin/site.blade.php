@extends('layouts.app')

@section('title', __('issues.site_title'))

@section('content')
@php
    use App\Support\SiteSections;
    $inp  = 'width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;';
    $lbl  = 'display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:4px;';
    $btn  = 'display:inline-block;padding:9px 18px;border-radius:8px;background:#0f172a;color:#fff;font-weight:700;font-size:14px;border:0;cursor:pointer;';
    $btnSm = 'display:inline-block;padding:7px 14px;border-radius:8px;background:#0f172a;color:#fff;font-weight:700;font-size:13px;border:0;cursor:pointer;';
    $card = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
    $chk  = 'display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#334155;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;background:#f8fafc;';
    $badge = 'display:inline-grid;place-items:center;width:28px;height:28px;border-radius:999px;background:#0f172a;color:#fff;font-weight:800;font-size:13px;flex-shrink:0;';
    $saveRow = 'margin-top:14px;padding-top:12px;border-top:1px dashed #e2e8f0;display:flex;justify-content:flex-end;';
    $pill = 'display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;font-size:12.5px;font-weight:700;color:#334155;text-decoration:none;white-space:nowrap;';
    $secs = $s['sections'];
    $nav  = $s['nav'];
    $footer = $s['footer'];
    $hs   = $s['hero_slider'];
    $N    = fn ($n) => \App\Support\IssuePresenter::num($n);
    // [anchor id, lang key] pairs drive both the quick-nav pills and the numbered section headers
    $toc = [
        ['appearance', 'issues.site_appearance_merged'],
        ['design',     'issues.site_design'],
        ['theme',      'issues.site_sec_theme'],
        ['float',      'issues.site_float'],
        ['popup',      'issues.site_popup'],
        ['slider',     'issues.site_hero_slider'],
        ['sections',   'issues.site_sections'],
        ['nav',        'issues.site_nav'],
        ['footer',     'issues.site_footer'],
    ];
@endphp

<div class="mx-auto px-4 py-6" style="max-width:920px;">
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

    {{-- ============ Sticky quick-nav: jump to any section ============ --}}
    <div style="position:sticky;top:0;z-index:40;margin:14px -4px 0;padding:10px 4px;background:rgba(248,250,252,.95);backdrop-filter:blur(6px);border-bottom:1px solid #e2e8f0;">
        <p style="font-size:11px;font-weight:700;color:#94a3b8;letter-spacing:.04em;text-transform:uppercase;margin:0 0 6px;">{{ __('issues.site_quick_nav') }}</p>
        <nav style="display:flex;gap:6px;overflow-x:auto;padding-bottom:2px;" aria-label="{{ __('issues.site_quick_nav') }}">
            @foreach ($toc as [$anchor, $key])
                <a href="#{{ $anchor }}" style="{{ $pill }}">{{ __($key) }}</a>
            @endforeach
        </nav>
        <p style="margin:8px 0 0;font-size:12px;color:#94a3b8;">{{ __('issues.site_save_hint') }}</p>
    </div>

    {{-- ============ 1. Appearance (own form — everything visual on ONE page) ============ --}}
    <section id="appearance" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="{{ $badge }}">{{ $N(1) }}</span>
            <div>
                <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_appearance_merged') }}</h2>
                <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_appearance_merged_hint') }}</p>
            </div>
        </div>
        <div style="margin-top:12px;">
            @include('admin._appearance-form')
        </div>
    </section>

    <form method="POST" action="{{ route('admin.site.save') }}" enctype="multipart/form-data">
        @csrf

        {{-- ============ 2. Design variants ============ --}}
        <section id="design" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="{{ $badge }}">{{ $N(2) }}</span>
                <div>
                    <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_design') }}</h2>
                    <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_design_hint') }}</p>
                </div>
            </div>
            <div style="margin-top:12px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
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
            <div style="{{ $saveRow }}">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>

        {{-- ============ 3. Colour & gradient theme ============ --}}
        @php $th = $s['theme'] ?? []; @endphp
        <section id="theme" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;" x-data="{ on: {{ ! empty($th['on']) ? 'true' : 'false' }} }">
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="{{ $badge }}">{{ $N(3) }}</span>
                    <div>
                        <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_sec_theme') }}</h2>
                        <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_sec_theme_hint') }}</p>
                    </div>
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
            <div style="{{ $saveRow }}">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>

        {{-- ============ 4. Floating buttons (install pill / report FAB / scroll-to-top) ============ --}}
        @php $fl = $s['float'] ?? []; @endphp
        <section id="float" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="{{ $badge }}">{{ $N(4) }}</span>
                <div>
                    <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_float') }}</h2>
                    <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_float_hint') }}</p>
                </div>
            </div>
            <div style="margin-top:12px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                @foreach ([
                    'pill' => ['title' => 'site_float_pill', 'desc' => 'site_float_pill_hint'],
                    'fab'  => ['title' => 'site_float_fab',  'desc' => 'site_float_fab_hint'],
                    'top'  => ['title' => 'site_float_top',  'desc' => 'site_float_top_hint'],
                ] as $b => $meta)
                    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;background:#f8fafc;" x-data="{ on: {{ ! empty($fl[$b . '_on']) ? 'true' : 'false' }} }">
                        <label style="font-size:14px;font-weight:800;color:#0f172a;cursor:pointer;display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="float_{{ $b }}_on" value="1" x-model="on" @checked(! empty($fl[$b . '_on']))> {{ __('issues.' . $meta['title']) }}
                        </label>
                        <p style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ __('issues.' . $meta['desc']) }}</p>
                        <div x-show="on" x-cloak style="margin-top:10px;display:flex;flex-direction:column;gap:10px;">
                            @if ($b !== 'top')
                                <div>
                                    <label style="{{ $lbl }}">{{ __('issues.site_float_label') }}</label>
                                    <input name="float_{{ $b }}_label" value="{{ old('float_' . $b . '_label', $fl[$b . '_label'] ?? '') }}" maxlength="60"
                                           placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">
                                </div>
                            @endif
                            <div>
                                <label style="{{ $lbl }}">{{ __('issues.site_float_color') }}</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="text" name="float_{{ $b }}_color" value="{{ old('float_' . $b . '_color', $fl[$b . '_color'] ?? '') }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" placeholder="#059669" style="{{ $inp }};max-width:110px;font-family:monospace;">
                                    <span style="display:inline-block;width:30px;height:30px;border-radius:8px;border:1px solid #e2e8f0;background:{{ $fl[$b . '_color'] ?? '#fff' }};" aria-hidden="true"></span>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div>
                                    <label style="{{ $lbl }}">{{ __('issues.site_float_size') }}</label>
                                    <select name="float_{{ $b }}_size" style="{{ $inp }}">
                                        @foreach (SiteSections::FLOAT_SIZES as $sz)
                                            <option value="{{ $sz }}" @selected(($fl[$b . '_size'] ?? 'md') === $sz)>{{ __('issues.site_float_size_' . $sz) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label style="{{ $lbl }}">{{ __('issues.site_float_pos') }}</label>
                                    <select name="float_{{ $b }}_pos" style="{{ $inp }}">
                                        @foreach (SiteSections::FLOAT_POS as $ps)
                                            <option value="{{ $ps }}" @selected(($fl[$b . '_pos'] ?? '') === $ps)>{{ __('issues.site_float_pos_' . $ps) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label style="{{ $lbl }}">{{ __('issues.site_float_bottom') }}</label>
                                    <input type="number" name="float_{{ $b }}_bottom" value="{{ old('float_' . $b . '_bottom', $fl[$b . '_bottom'] ?? 16) }}" min="0" max="400" step="4" style="{{ $inp }}">
                                </div>
                                <div>
                                    <label style="{{ $lbl }}">{{ __('issues.site_float_side') }}</label>
                                    <input type="number" name="float_{{ $b }}_side" value="{{ old('float_' . $b . '_side', $fl[$b . '_side'] ?? 16) }}" min="0" max="400" step="4" style="{{ $inp }}">
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div style="{{ $saveRow }}">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>

        {{-- ============ 5. Advertisement popup ============ --}}
        @php $pp = $s['popup'] ?? []; @endphp
        <section id="popup" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;" x-data="{ on: {{ ! empty($pp['on']) ? 'true' : 'false' }} }">
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="{{ $badge }}">{{ $N(5) }}</span>
                    <div>
                        <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_popup') }}</h2>
                        <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_popup_hint') }}</p>
                    </div>
                </div>
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#334155;cursor:pointer;">
                    <input type="checkbox" name="popup_on" value="1" x-model="on" @checked(! empty($pp['on']))> {{ __('issues.site_popup_on') }}
                </label>
            </div>

            <div x-show="on" x-cloak style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.site_popup_img') }}</label>
                    @if (($pp['img'] ?? '') !== '')
                        <img src="/{{ SiteSections::POPUP_DIR }}/{{ $pp['img'] }}" alt="" style="height:56px;width:auto;border-radius:8px;border:1px solid #e2e8f0;background:#fff;padding:2px;margin-bottom:6px;display:block;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#b91c1c;margin-bottom:4px;"><input type="checkbox" name="popup_img_remove" value="1"> {{ __('issues.site_slide_img_remove') }}</label>
                    @endif
                    <input type="file" name="popup_file" accept=".png,.jpg,.jpeg,.webp" style="font-size:13px;">
                    <p style="font-size:11px;color:#94a3b8;margin-top:4px;">{{ __('issues.site_popup_img_or') }}</p>
                    <input name="popup_img_url" value="{{ old('popup_img_url', $pp['img_url'] ?? '') }}" maxlength="300" placeholder="https://…" style="{{ $inp }};margin-top:6px;">
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.site_popup_text') }}</label>
                    <textarea name="popup_text" rows="3" maxlength="300" placeholder="{{ __('issues.site_popup_text_ph') }}" style="{{ $inp }}">{{ old('popup_text', $pp['text'] ?? '') }}</textarea>
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.site_popup_link') }}</label>
                    <input name="popup_link" value="{{ old('popup_link', $pp['link'] ?? '') }}" maxlength="300" placeholder="https://…" style="{{ $inp }}">
                    <p style="font-size:11px;color:#94a3b8;margin-top:4px;">{{ __('issues.site_popup_link_hint') }}</p>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="{{ $lbl }}">{{ __('issues.site_popup_delay') }}</label>
                        <input type="number" name="popup_delay" value="{{ old('popup_delay', $pp['delay'] ?? 3) }}" min="0" max="120" step="1" style="{{ $inp }}">
                    </div>
                    <div>
                        <label style="{{ $lbl }}">{{ __('issues.site_popup_freq') }}</label>
                        <select name="popup_freq" style="{{ $inp }}">
                            @foreach (SiteSections::POPUP_FREQS as $fq)
                                <option value="{{ $fq }}" @selected(($pp['freq'] ?? 'day') === $fq)>{{ __('issues.site_popup_freq_' . $fq) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div style="{{ $saveRow }}">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>


        {{-- ============ 6. Hero slider ============ --}}
        <section id="slider" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;" x-data="{ on: {{ $hs['on'] ? 'true' : 'false' }} }">
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="{{ $badge }}">{{ $N(6) }}</span>
                    <div>
                        <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_hero_slider') }}</h2>
                        <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_hero_slider_hint') }}</p>
                    </div>
                </div>
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#334155;cursor:pointer;">
                    <input type="checkbox" name="hero_slider_on" value="1" x-model="on" @checked($hs['on'])> {{ __('issues.site_hero_slider_on') }}
                </label>
            </div>

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
            <div style="{{ $saveRow }}">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>

        {{-- ============ 7. Homepage sections ============ --}}
        <section id="sections" style="margin-top:18px;scroll-margin-top:120px;">
            <div style="{{ $card }}">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="{{ $badge }}">{{ $N(7) }}</span>
                    <div>
                        <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_sections') }}</h2>
                        <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_sections_hint') }}</p>
                    </div>
                </div>
            </div>

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

                        @if (SiteSections::TEXT_FIELDS[$sec] || (SiteSections::BOOL_FIELDS[$sec] ?? []) || (SiteSections::SELECT_FIELDS[$sec] ?? []))
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

                                @foreach (SiteSections::SELECT_FIELDS[$sec] ?? [] as $f => $allowed)
                                    <div>
                                        <label style="{{ $lbl }}">{{ __('issues.site_f_' . $sec . '_' . $f) }}</label>
                                        <select name="sec_{{ $sec }}_{{ $f }}" style="{{ $inp }};max-width:260px;">
                                            @foreach ($allowed as $opt)
                                                <option value="{{ $opt }}" @selected(($row[$f] ?? $allowed[0]) === $opt)>{{ __('issues.site_f_' . $sec . '_' . $f . '_' . $opt) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div style="{{ $card }};margin-top:12px;padding-top:12px;padding-bottom:12px;display:flex;justify-content:flex-end;">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>

        {{-- ============ 8. Navbar ============ --}}
        <section id="nav" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="{{ $badge }}">{{ $N(8) }}</span>
                <div>
                    <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_nav') }}</h2>
                    <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">{{ __('issues.site_nav_hint') }}</p>
                </div>
            </div>
            <div style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                @foreach (SiteSections::NAV_ITEMS as $n)
                    <label style="{{ $chk }}">
                        <input type="checkbox" name="nav_{{ $n }}" value="1" @checked($nav[$n])> {{ __('issues.site_nav_' . $n) }}
                    </label>
                @endforeach
            </div>
            <div style="{{ $saveRow }}">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>

        {{-- ============ 9. Footer ============ --}}
        <section id="footer" style="{{ $card }};margin-top:18px;scroll-margin-top:120px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="{{ $badge }}">{{ $N(9) }}</span>
                <div>
                    <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.site_footer') }}</h2>
                </div>
            </div>
            <div style="margin-top:12px;display:flex;flex-direction:column;gap:10px;">
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
            <div style="{{ $saveRow }}">
                <button type="submit" style="{{ $btnSm }}">{{ __('issues.site_save_section') }}</button>
            </div>
        </section>

        <button type="submit" style="{{ $btn }};margin-top:20px;width:100%;padding:13px 18px;font-size:15px;">{{ __('issues.site_save_all') }}</button>
    </form>
</div>
@endsection
