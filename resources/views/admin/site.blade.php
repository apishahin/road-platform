@extends('layouts.app')

@section('title', __('issues.site_title'))

@section('content')
@php
    use App\Support\SiteSections;
    $inp  = 'width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;';
    $lbl  = 'display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:4px;';
    $btn  = 'display:inline-block;padding:9px 18px;border-radius:8px;background:#0f172a;color:#fff;font-weight:700;font-size:14px;border:0;cursor:pointer;';
    $card = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
    $secs = $s['sections'];
    $nav  = $s['nav'];
    $footer = $s['footer'];
@endphp

<div class="mx-auto max-w-3xl px-4 py-6" x-data="{ allOn: true }">
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

    <form method="POST" action="{{ route('admin.site.save') }}">
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

        {{-- ============ Homepage sections ============ --}}
        <h2 style="margin-top:20px;font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.site_sections') }}</h2>
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

                    @if (SiteSections::TEXT_FIELDS[$sec])
                        <div x-show="on" x-cloak style="margin-top:12px;display:grid;grid-template-columns:1fr;gap:10px;">
                            @foreach (SiteSections::TEXT_FIELDS[$sec] as $f)
                                <div>
                                    <label style="{{ $lbl }}">{{ __('issues.site_f_' . $sec . '_' . $f) }}</label>
                                    @if (strlen($f) > 1 || $f === 'text')
                                        <input name="sec_{{ $sec }}_{{ $f }}" value="{{ old('sec_' . $sec . '_' . $f, $row[$f] ?? '') }}" maxlength="500"
                                               placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">
                                    @else
                                        <input name="sec_{{ $sec }}_{{ $f }}" value="{{ old('sec_' . $sec . '_' . $f, $row[$f] ?? '') }}" maxlength="500"
                                               placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">
                                    @endif
                                </div>
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
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#334155;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;background:#f8fafc;">
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
            <div>
                <label style="{{ $lbl }}">{{ __('issues.site_footer_text') }}</label>
                <textarea name="footer_text" rows="3" maxlength="1000" placeholder="{{ __('issues.site_placeholder_default') }}" style="{{ $inp }}">{{ old('footer_text', $footer['text']) }}</textarea>
            </div>
        </div>

        <button type="submit" style="{{ $btn }};margin-top:18px;">{{ __('issues.fund_save') }}</button>
    </form>
</div>
@endsection
