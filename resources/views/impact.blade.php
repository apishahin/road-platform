@extends('layouts.app')

@section('title', __('issues.impact_title'))
@section('description', __('issues.impact_sub'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8" data-impact>
    <h1 class="text-2xl font-bold sm:text-3xl">{{ __('issues.impact_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.impact_sub') }}</p>

    {{-- Stat cards --}}
    <div style="margin-top:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
        @php
            $cards = [
                [$total,     __('issues.impact_total'),     '#0f172a'],
                [$repaired,  __('issues.impact_repaired'),  '#15803d'],
                [$rate . '%', __('issues.impact_rate'),     '#b45309'],
                [$open,      __('issues.impact_open'),      '#0369a1'],
                [$avgDays === null ? '—' : \App\Support\IssuePresenter::num($avgDays), __('issues.impact_avg_days'), '#7c3aed'],
                [\App\Support\IssuePresenter::num($districts), __('issues.impact_districts'), '#be185d'],
            ];
        @endphp
        @foreach ($cards as [$val, $label, $color])
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 14px;text-align:center;">
                <div style="font-size:1.7rem;font-weight:800;color:{{ $color }};">{{ is_numeric($val) ? \App\Support\IssuePresenter::num($val) : $val }}</div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    {{-- Monthly chart: reported vs repaired --}}
    <div style="margin-top:20px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;">
        <h2 style="font-weight:700;color:#0f172a;">{{ __('issues.impact_chart') }}</h2>
        <div style="margin-top:6px;display:flex;gap:16px;font-size:12px;color:#64748b;">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#f59e0b;"></span> {{ __('issues.impact_legend_reported') }}</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#16a34a;"></span> {{ __('issues.impact_legend_fixed') }}</span>
        </div>
        @php $max = max(1, ...array_map(fn ($m) => max($m['reported'], $m['fixed']), array_values($months))); @endphp
        <div style="margin-top:16px;display:flex;align-items:flex-end;gap:14px;height:160px;">
            @foreach ($months as $m)
                <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:2px;height:100%;">
                    <div style="display:flex;align-items:flex-end;gap:4px;height:130px;">
                        <div title="{{ $m['label'] }} — {{ $m['reported'] }}" style="width:14px;background:#f59e0b;border-radius:3px 3px 0 0;height:{{ max(2, (int) round($m['reported'] / $max * 130)) }}px;"></div>
                        <div title="{{ $m['label'] }} — {{ $m['fixed'] }}" style="width:14px;background:#16a34a;border-radius:3px 3px 0 0;height:{{ max(2, (int) round($m['fixed'] / $max * 130)) }}px;"></div>
                    </div>
                    <div style="font-size:11px;color:#94a3b8;">{{ $m['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top categories --}}
    @if ($topCats->isNotEmpty())
    <div style="margin-top:20px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;">
        <h2 style="font-weight:700;color:#0f172a;">{{ __('issues.impact_top_cats') }}</h2>
        @php $maxCat = max(1, (int) $topCats->max()); @endphp
        <div style="margin-top:12px;display:grid;gap:8px;">
            @foreach ($topCats as $catId => $c)
                @php $cat = $catNames[$catId] ?? null; @endphp
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:130px;flex-shrink:0;font-size:13px;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $cat?->name() ?? '—' }}</div>
                    <div style="flex:1;background:#f1f5f9;border-radius:6px;height:14px;overflow:hidden;">
                        <div style="background:#0ea5e9;height:100%;width:{{ (int) round($c / $maxCat * 100) }}%;"></div>
                    </div>
                    <div style="width:36px;text-align:right;font-size:13px;font-weight:700;color:#0f172a;">{{ \App\Support\IssuePresenter::num($c) }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <p style="margin-top:16px;font-size:12px;color:#94a3b8;">{{ __('issues.impact_note') }}</p>
</div>
@endsection
