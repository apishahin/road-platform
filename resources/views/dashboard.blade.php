@extends('layouts.app')

@section('title', __('issues.dash_title'))

@section('content')
@php
    $N    = fn ($n) => \App\Support\IssuePresenter::num($n);
    $card = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
    $maxMonth = max(1, ...array_map(fn ($m) => $m['n'], $months));
    $maxDist  = max(1, ...array_map(fn ($d) => $d['total'], $districts ?: [['total' => 1]]));
    $maxCat   = max(1, ...array_map(fn ($c) => $c['n'], $categories ?: [['n' => 1]]));
@endphp

<div class="mx-auto px-4 py-6" style="max-width:1000px;" data-dashboard>
    <h1 style="font-size:24px;font-weight:800;color:#0f172a;">{{ __('issues.dash_title') }}</h1>
    <p style="margin-top:2px;font-size:14px;color:#64748b;">{{ __('issues.dash_sub') }}</p>

    {{-- Headline counters --}}
    <div style="margin-top:16px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
        <div style="{{ $card }}text-align:center;background:#f0fdf4;border-color:#bbf7d0;">
            <div style="font-size:28px;font-weight:800;color:#15803d;">{{ $N($totals['reports']) }}</div>
            <div style="font-size:13px;font-weight:600;color:#334155;margin-top:2px;">{{ __('issues.dash_reports') }}</div>
        </div>
        <div style="{{ $card }}text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#0f172a;">{{ $N($totals['active']) }}</div>
            <div style="font-size:13px;font-weight:600;color:#334155;margin-top:2px;">{{ __('issues.dash_active') }}</div>
        </div>
        <div style="{{ $card }}text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#059669;">{{ $N($totals['repaired']) }}</div>
            <div style="font-size:13px;font-weight:600;color:#334155;margin-top:2px;">{{ __('issues.dash_repaired') }}</div>
        </div>
        <div style="{{ $card }}text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#0369a1;">{{ $N($totals['districts']) }}</div>
            <div style="font-size:13px;font-weight:600;color:#334155;margin-top:2px;">{{ __('issues.dash_districts') }}</div>
        </div>
    </div>

    @if ($totals['active'] === 0 && $totals['reports'] === 0)
        <div style="{{ $card }};margin-top:16px;text-align:center;color:#64748b;font-size:14px;">{{ __('issues.dash_empty') }}</div>
    @else
        {{-- Monthly trend --}}
        <div style="{{ $card }};margin-top:16px;">
            <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.dash_trend') }}</h2>
            <div style="margin-top:14px;display:flex;align-items:flex-end;gap:6px;overflow-x:auto;padding-bottom:4px;min-height:150px;">
                @foreach ($months as $m)
                    @php $h = (int) round($m['n'] / $maxMonth * 110); @endphp
                    <div style="flex:1;min-width:44px;display:flex;flex-direction:column;align-items:center;gap:4px;" title="{{ $m['n'] }}">
                        <span style="font-size:11px;font-weight:700;color:#475569;">{{ $m['n'] > 0 ? $N($m['n']) : '' }}</span>
                        <div style="width:100%;max-width:38px;height:{{ max(4, $h) }}px;border-radius:6px 6px 0 0;background:{{ $m['n'] > 0 ? '#22c55e' : '#e2e8f0' }};"></div>
                        <span style="font-size:11px;color:#64748b;">{{ trans('issues.dash_m' . $m['label']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- District-wise --}}
        @if (count($districts) > 0)
            <div style="{{ $card }};margin-top:16px;">
                <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.dash_by_district') }}</h2>
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                    @foreach ($districts as $d)
                        <div style="display:grid;grid-template-columns:minmax(90px,140px) 1fr auto;align-items:center;gap:10px;" data-district-row>
                            <div style="font-size:13.5px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $d['name'] }}</div>
                            <div style="background:#f1f5f9;border-radius:999px;height:16px;overflow:hidden;">
                                <div style="height:100%;width:{{ (int) round($d['total'] / $maxDist * 100) }}%;background:linear-gradient(90deg,#86efac,#22c55e);border-radius:999px;"></div>
                            </div>
                            <div style="font-size:12.5px;color:#475569;white-space:nowrap;">
                                <strong style="color:#0f172a;">{{ $N($d['total']) }}</strong>
                                · {{ __('issues.dash_solved_short') }} <span style="color:#059669;font-weight:700;">{{ $N($d['repaired']) }}</span>
                                ({{ $N($d['pct']) }}%)
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Category-wise --}}
        @if (count($categories) > 0)
            <div style="{{ $card }};margin-top:16px;">
                <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">{{ __('issues.dash_by_category') }}</h2>
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                    @foreach ($categories as $c)
                        <div style="display:grid;grid-template-columns:minmax(90px,140px) 1fr auto;align-items:center;gap:10px;">
                            <div style="font-size:13.5px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $c['name'] }}</div>
                            <div style="background:#f1f5f9;border-radius:999px;height:16px;overflow:hidden;">
                                <div style="height:100%;width:{{ (int) round($c['n'] / $maxCat * 100) }}%;background:linear-gradient(90deg,#7dd3fc,#0ea5e9);border-radius:999px;"></div>
                            </div>
                            <div style="font-size:13px;font-weight:700;color:#0f172a;">{{ $N($c['n']) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
