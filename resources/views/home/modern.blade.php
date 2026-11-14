@include('home._rp-css')
@php
    $S = \App\Support\SiteSections::class;
    $P = \App\Support\IssuePresenter::class;
    $areaName = function ($issue) {
        $a = $issue->adminArea ?? null;
        if (! $a) { return ''; }
        return app()->getLocale() === 'bn' ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn);
    };
    $stClass = ['new' => 'status-new', 'started' => 'status-active', 'repaired' => 'status-done'];
@endphp
<div data-home-design="modern" class="rp-home">

{{-- ═══ SECTION 01: Hero — photo slider (or the plain green hero when no slide has a photo) ═══ --}}
@if ($S::on('hero'))
    @include('home._slider', ['heroSlides' => $S::heroSlider()['on'] ? $S::heroSlider()['slides'] : []])
@endif

{{-- ═══ SECTION 02: Fund strip overlapping the hero ═══ --}}
@if ($S::on('fund'))
    <div class="{{ $S::on('hero') ? '' : 'fund-nohero' }}" style="{{ $S::on('hero') ? '' : 'padding-top:22px;' }}">
        @include('fund._home-box-modern')
    </div>
@endif

{{-- ═══ SECTION 03: Live map + recent issues ═══ --}}
@if ($S::on('map'))
<section class="rp-wrap rp-sec">
    <div class="section-head">
        <h2 class="section-title">
            <span class="symbol"><svg style="width:17px;height:17px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25 3 17.25V3.75L9 6.75m0 13.5 6 3m-6-3V6.75m6 16.5 6-3V6.75l-6-3m0 16.5V3.75m0 0L9 6.75M15 3.75l6 3"/></svg></span>
            {{ $S::txt('map', 'title', __('issues.map_sec_title')) }}
        </h2>
    </div>
    <div class="map-grid">
        <div class="map-panel">
            <div class="map-top" style="justify-content:flex-end;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button id="locate-btn" type="button" class="small-outline">◎ {{ __('Use my location') }}</button>
                    <a href="{{ url('/report') }}" class="small-outline">＋ {{ __('issues.report_new_short') }}</a>
                </div>
            </div>
            <div id="status-filter" class="rp-chips" role="group" aria-label="{{ __('issues.filter_label') }}" data-none="{{ __('issues.filter_none') }}">
                @foreach (['all' => 'issues.filter_all', 'new' => 'issues.filter_new', 'started' => 'issues.filter_started', 'repaired' => 'issues.filter_repaired'] as $key => $label)
                    <button type="button" data-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}"
                            class="rp-chip" style="{{ $key === 'all' ? 'background-color:#0f172a;color:#ffffff;border-color:#0f172a;' : '' }}">
                        {{ __($label) }}
                    </button>
                @endforeach
            </div>
            <div id="home-map" class="map-live"></div>
            <div class="map-legend">
                <span><i class="legend-dot" style="background:#e53935"></i>{{ __('issues.legend_new') }}</span>
                <span><i class="legend-dot" style="background:#2584df"></i>{{ __('issues.legend_ongoing') }}</span>
                <span><i class="legend-dot" style="background:#8b5cf6"></i>{{ __('issues.legend_repairing') }}</span>
                <span><i class="legend-dot" style="background:#078a48"></i>{{ __('issues.legend_done') }}</span>
            </div>
        </div>
        <aside class="recent-panel">
            <div class="recent-head">
                <h3 id="list-title">{{ __('Recently active') }}</h3>
                <a class="text-link" href="{{ url('/issues') }}">{{ __('issues.home_view_all') }}</a>
            </div>
            <p id="locate-status" style="font-size:11px;color:var(--muted);min-height:4px;margin:0 0 2px;" aria-live="polite"></p>
            <div id="issue-list">
                @forelse ($recent->take(7) as $issue)
                    @php $d = $P::display($issue); $ph = $photos[$issue->id] ?? null; @endphp
                    <a href="{{ url('/issues/' . $issue->public_id) }}" class="issue">
                        @if ($ph)
                            <img class="thumb" src="{{ $ph }}" alt="{{ $issue->title }}" loading="lazy">
                        @else
                            <span class="thumb thumb-ph" aria-hidden="true">
                                <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.14-7.5 11.25-7.5 11.25S4.5 17.64 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                            </span>
                        @endif
                        <div>
                            <div class="issue-title">
                                <h4>{{ $issue->title }}</h4>
                                <span class="status {{ $stClass[$P::group($issue)] ?? 'status-new' }}">{{ $d['label'] }}</span>
                            </div>
                            @if ($areaName($issue) !== '')
                                <p>⌖ {{ $areaName($issue) }}</p>
                            @endif
                            <p>◷ {{ $issue->last_activity_at?->diffForHumans() }}</p>
                        </div>
                    </a>
                @empty
                    <p style="border:1px dashed var(--line);border-radius:12px;padding:16px;font-size:13px;color:var(--body);">
                        {{ __('No reports yet. Be the first to report a problem.') }}
                    </p>
                @endforelse
            </div>
        </aside>
    </div>
</section>
@endif

{{-- ═══ SECTION 04: Stats ribbon ═══ --}}
@include('home._ribbon')

{{-- ═══ SECTION 05: How it works (4 numbered steps) ═══ --}}
@if ($S::on('how'))
<section class="rp-wrap rp-sec">
    <div class="section-head">
        <h2 class="section-title">
            <span class="symbol"><svg style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.2"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.09a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg></span>
            {{ __('issues.how_title') }}
        </h2>
        @php $howPage = \App\Models\Page::where('slug', 'how-it-works')->where('is_published', true)->first(); @endphp
        @if ($howPage)
            <a href="{{ route('page.show', $howPage->slug) }}" class="text-link">{{ __('issues.how_more') }} →</a>
        @endif
    </div>
    @php
    $hwIcons = [
        '<path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 1.69-1.69a1.88 1.88 0 1 1 2.65 2.65L3.83 22.83a2.83 2.83 0 0 1-1.41.76L1 24l.41-2.42a2.83 2.83 0 0 1 .76-1.41Z"/>',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path stroke-linecap="round" d="M4 22v-7"/>',
    ];
    @endphp
    <div class="steps">
    @foreach ([
        ['s1t', 'issues.how1_title', 's1x', 'issues.how1_text', 1],
        ['s2t', 'issues.how2_title', 's2x', 'issues.how2_text', 2],
        ['s3t', 'issues.how3_title', 's3x', 'issues.how3_text', 3],
        ['s4t', 'issues.how4_title', 's4x', 'issues.how4_text', 4],
    ] as [$tk, $tdef, $xk, $xdef, $num])
        <div class="step-card">
            <div class="step-top">
                <span class="step-number">{{ $P::num($num) }}</span>
                <span class="step-icon"><svg style="width:20px;height:20px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $hwIcons[$num - 1] !!}</svg></span>
            </div>
            <h3>{{ $S::txt('how', $tk, __($tdef)) }}</h3>
            <p>{{ $S::txt('how', $xk, __($xdef)) }}</p>
        </div>
    @endforeach
    </div>
</section>
@endif

{{-- ═══ SECTION 06: Completed repair examples ═══ --}}
@if ($S::on('repaired') && isset($repaired) && count($repaired) > 0)
<section class="rp-wrap rp-sec">
    <div class="section-head">
        <h2 class="section-title">
            <span class="symbol"><svg style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8m-4-4v4m-6-4h12a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1h-3.18a4 4 0 0 0-7.64 0H6a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1Z"/></svg></span>
            {{ $S::txt('repaired', 'title', __('issues.home_repaired_title')) }}
        </h2>
        <a href="{{ route('issues.index', ['tab' => 'repaired']) }}" class="text-link">{{ __('issues.home_view_all') }} →</a>
    </div>
    <div class="repairs">
        @foreach ($repaired->take(8) as $issue)
            @php $ph = $photos[$issue->id] ?? null; @endphp
            <a href="{{ url('/issues/' . $issue->public_id) }}" class="repair-card">
                <div class="repair-image">
                    @if ($ph)
                        <img src="{{ $ph }}" alt="{{ $issue->title }}" loading="lazy">
                    @else
                        <span class="repair-ph" aria-hidden="true">
                            <svg style="width:30px;height:30px;" fill="none" stroke="currentColor" stroke-width="1.3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75 7.5 10.5l3.75 3.75 6-6L21.75 12.75M2.25 15.75V19.5h19.5v-6.75"/></svg>
                        </span>
                    @endif
                    <span class="status">✓ {{ __('issues.fix_badge') }}</span>
                </div>
                <div class="repair-info">
                    <strong>{{ $areaName($issue) !== '' ? $areaName($issue) : $issue->title }}</strong>
                    <small>{{ $issue->last_activity_at?->diffForHumans() }}</small>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ═══ SECTION 07: Community CTA band ═══ --}}
@if ($S::on('cta'))
    @include('home._cta')
@endif

@if ($S::on('privacy'))
    <div class="rp-wrap">
        <p class="rp-privacy">{{ $S::txt('privacy', 'text', __('issues.home_privacy')) }}</p>
    </div>
@endif

</div>
