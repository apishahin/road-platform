{{--
    Live statistics panel (counters + trend chart + district list + category chart).
    Used by the home page (ribbon slot) and by /dashboard.
    Admin controls: /admin/site → statistics panel section (toggles, labels,
    chart styles, colours). Pass $forceAll = true to render every panel.
--}}
@php
    $S  = \App\Support\SiteSections::class;
    $N  = fn ($v) => \App\Support\IssuePresenter::num($v);
    $rc = $S::all()['sections']['ribbon'] ?? [];
    $forceAll = $forceAll ?? false;
    $show = fn (string $k) => $forceAll || ! empty($rc[$k]);

    $dash = app(\App\Services\DashboardData::class)->build();
    $trendStyle = $S::sel('ribbon', 'trend_style') ?: 'vbars';
    $distStyle  = $S::sel('ribbon', 'dist_style') ?: 'bars';
    $catStyle   = $S::sel('ribbon', 'cat_style') ?: 'bars';
    $color1     = $rc['color1'] ?? '#22c55e';
    $color2     = $rc['color2'] ?? '#0ea5e9';

    $card  = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
    $h2sty = 'font-size:16px;font-weight:800;color:#0f172a;margin:0;';

    $icons = [
        'm1' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4 4.5 8.5 12 13l7.5-4.5L12 4Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12 7.5 4.5L19.5 12M4.5 15.5 12 20l7.5-4.5"/>',
        'm2' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
        'm3' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'm4' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.14-7.5 11.25-7.5 11.25S4.5 17.64 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>',
    ];
    $counters = [];
    try {
        if ($show('m1')) {
            $counters[] = ['n' => $N(\App\Models\Report::where('moderation', 'approved')->count()) . '+',
                           'l' => $S::txt('ribbon', 'l1', __('issues.ribbon_reports')), 'i' => $icons['m1'], 'c' => '#15803d'];
        }
        if ($show('m2')) {
            $counters[] = ['n' => $N(\App\Models\Issue::active()->where('progress_status', 'repaired')->count()) . '+',
                           'l' => $S::txt('ribbon', 'l2', __('issues.ribbon_repaired')), 'i' => $icons['m2'], 'c' => '#059669'];
        }
        if ($show('m3')) {
            $counters[] = ['n' => $N(\App\Models\User::count()) . '+',
                           'l' => $S::txt('ribbon', 'l3', __('issues.ribbon_users')), 'i' => $icons['m3'], 'c' => '#0369a1'];
        }
        if ($show('m4')) {
            $override = trim($S::txt('ribbon', 'district_n', ''));
            $count    = \App\Models\Issue::activeDistrictCount();
            if ($override !== '' || $count > 0) {
                $counters[] = ['n' => $override !== '' ? $override : $N($count),
                               'l' => $S::txt('ribbon', 'l4', __('issues.ribbon_districts')), 'i' => $icons['m4'], 'c' => '#7c3aed'];
            }
        }
    } catch (\Throwable $e) {
        $counters = [];
    }

    $months     = $dash['months'];
    $districts  = $dash['districts'];
    $categories = $dash['categories'];
    $maxMonth = max(1, ...array_map(fn ($m) => $m['n'], $months));
    $maxDist  = max(1, ...array_map(fn ($d) => $d['total'], $districts ?: [['total' => 1]]));
    $maxCat   = max(1, ...array_map(fn ($c) => $c['n'], $categories ?: [['n' => 1]]));
    $palette  = [$color1, $color2, '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6', '#f97316', '#64748b'];
    $catTotal = max(1, array_sum(array_map(fn ($c) => $c['n'], $categories)));

    // donut rows are capped at 7 slices + "others"
    $cap = function (array $rows, callable $getN, callable $getName) use ($catTotal) {
        if (count($rows) <= 7) {
            return $rows;
        }
        $top = array_slice($rows, 0, 7);
        $rest = array_slice($rows, 7);
        $top[] = ['name' => __('issues.dash_others'), 'n' => array_sum(array_map($getN, $rest)), 'total' => array_sum(array_map($getN, $rest))];

        return $top;
    };
    $distRows = array_map(fn ($d) => ['name' => $d['name'], 'n' => $d['total']], $districts);
    $distRows = $cap($distRows, fn ($r) => $r['n'], fn ($r) => $r['name']);
    $distTotal = max(1, array_sum(array_map(fn ($r) => $r['n'], $distRows)));
    $catRows  = $cap($categories, fn ($c) => $c['n'], fn ($c) => $c['name']);

    // line-chart points (viewBox 600x160)
    $pts = [];
    foreach (array_values($months) as $i => $m) {
        $x = 20 + $i * (560 / 11);
        $y = 145 - ($m['n'] / $maxMonth * 125);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $linePts = implode(' ', $pts);
    $areaPts = '20,150 ' . $linePts . ' 580,150';
@endphp

<style>
    .rp-dash-row{display:grid;grid-template-columns:minmax(90px,150px) 1fr auto;align-items:center;gap:10px;}
    .rp-dash-wrap2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .rp-vbars{display:flex;align-items:flex-end;gap:4px;padding-bottom:4px;min-height:150px;}
    .rp-vbars > div{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;}
    .rp-vbars .vb{width:100%;max-width:38px;border-radius:6px 6px 0 0;}
    .rp-vbars .bl{font-size:11px;color:#64748b;white-space:nowrap;}
    .rp-vbars .bv{font-size:11px;font-weight:700;color:#475569;}
    @media (max-width:640px){
        .rp-dash-row{grid-template-columns:1fr;gap:4px;padding-bottom:6px;border-bottom:1px dashed #f1f5f9;}
        .rp-dash-wrap2{grid-template-columns:1fr;}
        .rp-vbars{gap:2px;min-height:120px;}
        .rp-vbars .bl{font-size:9px;letter-spacing:-.02em;}
        .rp-vbars .bv{font-size:9px;}
    }
</style>

<div data-stats-ribbon data-stats-panel style="display:flex;flex-direction:column;gap:14px;">

    {{-- counters --}}
    @if (count($counters) > 0)
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">
            @foreach ($counters as $ct)
                <div style="{{ $card }}text-align:center;">
                    <span style="display:inline-grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#f0fdf4;color:{{ $ct['c'] }};" aria-hidden="true">
                        <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">{!! $ct['i'] !!}</svg>
                    </span>
                    <div data-counter-num style="font-size:26px;font-weight:800;color:{{ $ct['c'] }};margin-top:6px;">{{ $ct['n'] }}</div>
                    <div style="font-size:13px;font-weight:600;color:#334155;margin-top:2px;">{{ $ct['l'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- monthly trend --}}
    @if ($show('show_trend'))
        <div style="{{ $card }}" data-trend="{{ $trendStyle }}">
            <h2 style="{{ $h2sty }}">{{ __('issues.dash_trend') }}</h2>
            @if ($trendStyle === 'hbars')
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:6px;">
                    @foreach ($months as $m)
                        <div class="rp-dash-row" style="grid-template-columns:52px 1fr auto;">
                            <div style="font-size:12px;font-weight:700;color:#475569;">{{ trans('issues.dash_m' . $m['label']) }}</div>
                            <div style="background:#f1f5f9;border-radius:999px;height:14px;overflow:hidden;">
                                <div style="height:100%;width:{{ (int) round($m['n'] / $maxMonth * 100) }}%;background:linear-gradient(90deg,{{ $color2 }},{{ $color1 }});border-radius:999px;"></div>
                            </div>
                            <div style="font-size:12.5px;font-weight:700;color:#0f172a;min-width:24px;text-align:right;">{{ $m['n'] > 0 ? $N($m['n']) : '—' }}</div>
                        </div>
                    @endforeach
                </div>
            @elseif ($trendStyle === 'line')
                <div style="margin-top:12px;">
                    <svg viewBox="0 0 600 170" style="width:100%;height:auto;display:block;" role="img" aria-label="{{ __('issues.dash_trend') }}">
                        <line x1="20" y1="150" x2="580" y2="150" stroke="#e2e8f0" stroke-width="1"/>
                        <polygon points="{{ $areaPts }}" fill="{{ $color1 }}" opacity="0.15"/>
                        <polyline points="{{ $linePts }}" fill="none" stroke="{{ $color1 }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        @foreach (array_values($months) as $i => $m)
                            @if ($m['n'] > 0)
                                @php $x = 20 + $i * (560 / 11); $y = 145 - ($m['n'] / $maxMonth * 125); @endphp
                                <circle cx="{{ round($x, 1) }}" cy="{{ round($y, 1) }}" r="3.5" fill="{{ $color1 }}"/>
                                <text x="{{ round($x, 1) }}" y="{{ round($y - 8, 1) }}" text-anchor="middle" style="font-size:11px;font-weight:700;fill:#475569;">{{ $N($m['n']) }}</text>
                            @endif
                        @endforeach
                        @foreach (array_values($months) as $i => $m)
                            <text x="{{ round(20 + $i * (560 / 11), 1) }}" y="165" text-anchor="middle" style="font-size:10px;fill:#64748b;">{{ trans('issues.dash_m' . $m['label']) }}</text>
                        @endforeach
                    </svg>
                </div>
            @else
                <div class="rp-vbars" style="margin-top:14px;">
                    @foreach ($months as $m)
                        @php $h = (int) round($m['n'] / $maxMonth * 110); @endphp
                        <div title="{{ $m['n'] }}">
                            <span class="bv">{{ $m['n'] > 0 ? $N($m['n']) : '' }}</span>
                            <div class="vb" style="height:{{ max(4, $h) }}px;background:{{ $m['n'] > 0 ? $color1 : '#e2e8f0' }};"></div>
                            <span class="bl">{{ trans('issues.dash_m' . $m['label']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- district + category side by side on wide screens --}}
    @if ($show('show_districts') || $show('show_categories'))
        <div class="rp-dash-wrap2">
            @if ($show('show_districts') && count($districts) > 0)
                <div style="{{ $card }}" data-distchart="{{ $distStyle }}">
                    <h2 style="{{ $h2sty }}">{{ __('issues.dash_by_district') }}</h2>
                    @if ($distStyle === 'donut')
                        @include('partials._donut', ['rows' => $distRows, 'palette' => $palette, 'total' => $distTotal, 'thick' => 7, 'size' => 42])
                    @else
                        <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                            @foreach ($districts as $d)
                                <div class="rp-dash-row" data-district-row>
                                    <div style="font-size:13.5px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $d['name'] }}</div>
                                    <div style="background:#f1f5f9;border-radius:999px;height:16px;overflow:hidden;">
                                        <div style="height:100%;width:{{ (int) round($d['total'] / $maxDist * 100) }}%;background:linear-gradient(90deg,{{ $color2 }},{{ $color1 }});border-radius:999px;"></div>
                                    </div>
                                    <div style="font-size:12.5px;color:#475569;white-space:nowrap;">
                                        <strong style="color:#0f172a;">{{ $N($d['total']) }}</strong>
                                        · {{ __('issues.dash_solved_short') }} <span style="color:#059669;font-weight:700;">{{ $N($d['repaired']) }}</span>
                                        ({{ $N($d['pct']) }}%)
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if ($show('show_categories') && count($categories) > 0)
                <div style="{{ $card }}" data-catchart="{{ $catStyle }}">
                    <h2 style="{{ $h2sty }}">{{ __('issues.dash_by_category') }}</h2>
                    @if ($catStyle === 'donut')
                        @include('partials._donut', ['rows' => $catRows, 'palette' => $palette, 'total' => $catTotal, 'thick' => 7, 'size' => 42])
                    @elseif ($catStyle === 'pie')
                        @include('partials._donut', ['rows' => $catRows, 'palette' => $palette, 'total' => $catTotal, 'thick' => 31.8, 'size' => 64])
                    @else
                        <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                            @foreach ($categories as $c)
                                <div class="rp-dash-row">
                                    <div style="font-size:13.5px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $c['name'] }}</div>
                                    <div style="background:#f1f5f9;border-radius:999px;height:16px;overflow:hidden;">
                                        <div style="height:100%;width:{{ (int) round($c['n'] / $maxCat * 100) }}%;background:linear-gradient(90deg,{{ $color2 }},{{ $color1 }});border-radius:999px;"></div>
                                    </div>
                                    <div style="font-size:13px;font-weight:700;color:#0f172a;">{{ $N($c['n']) }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
