@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8" data-analytics>
    <h1 class="text-2xl font-bold">{{ __('issues.an_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.an_privacy') }}</p>

    {{-- date range picker --}}
    <form method="GET" action="{{ route('admin.analytics.index') }}" class="mt-4 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3" data-an-range>
        <input type="date" name="from" value="{{ $fromStr }}" max="{{ now()->toDateString() }}" class="rounded-lg border-slate-300 text-sm">
        <span class="text-sm text-slate-500">→</span>
        <input type="date" name="to" value="{{ $toStr }}" max="{{ now()->toDateString() }}" class="rounded-lg border-slate-300 text-sm">
        <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.an_apply') }}</button>
        <a href="{{ route('admin.analytics.csv', ['from' => $fromStr, 'to' => $toStr]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100">{{ __('issues.export_csv') }}</a>
        <div class="ms-auto flex flex-wrap gap-1.5 text-xs">
            @foreach ([
                __('issues.an_preset_today')     => [now()->toDateString(), now()->toDateString()],
                __('issues.an_preset_yesterday') => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
                __('issues.an_preset_7d')        => [now()->subDays(6)->toDateString(), now()->toDateString()],
                __('issues.an_preset_30d')       => [now()->subDays(29)->toDateString(), now()->toDateString()],
            ] as $label => [$f, $t])
                <a href="{{ route('admin.analytics.index', ['from' => $f, 'to' => $t]) }}"
                   class="rounded-full border px-2.5 py-1 {{ $fromStr === $f && $toStr === $t ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    {{-- headline counters --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center">
            <div class="text-2xl font-bold text-brand-700">{{ $todayViews }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ __('issues.an_today_views') }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center">
            <div class="text-2xl font-bold text-brand-700">{{ $todayUniques }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ __('issues.an_today_uniques') }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $totalRange }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ __('issues.an_total_range', ['days' => $numDays]) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $avgRange }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ __('issues.an_avg_range', ['days' => $numDays]) }}</div>
        </div>
    </div>

    {{-- 30-day chart --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-an-chart>
        <div class="font-semibold">{{ __('issues.an_chart_range', ['from' => $fromStr, 'to' => $toStr]) }}</div>
        <div class="mt-4 flex items-end gap-[3px]" style="height:140px;">
            @foreach ($days as $d)
                <div class="flex min-w-0 flex-1 flex-col items-center justify-end" title="{{ $d['label'] }}: {{ $d['views'] }} {{ __('issues.an_views') }}, {{ $d['uniques'] }} {{ __('issues.an_uniques') }}">
                    <div class="w-full rounded-t"
                         style="height:{{ max(2, round($d['views'] / $max * 120)) }}px;background:{{ $d['date'] === now()->toDateString() ? '#059669' : '#a7f3d0' }};"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-[10px] text-slate-400">
            <span>{{ $days[0]['label'] }}</span><span>{{ $days[intdiv(count($days) - 1, 2)]['label'] }}</span><span>{{ $days[count($days) - 1]['label'] }}</span>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        {{-- top pages --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5" data-an-pages>
            <div class="font-semibold">{{ __('issues.an_top_pages') }}</div>
            <div class="mt-2 divide-y divide-slate-100 text-sm">
                @forelse ($topPages as $p)
                    <div class="flex items-center justify-between gap-3 py-1.5">
                        <span class="truncate text-slate-700">{{ $p->path }}</span>
                        <span class="shrink-0 font-semibold">{{ $p->n }}</span>
                    </div>
                @empty
                    <div class="py-2 text-slate-500">{{ __('issues.an_none') }}</div>
                @endforelse
            </div>
        </div>

        {{-- referrers --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5" data-an-refs>
            <div class="font-semibold">{{ __('issues.an_top_refs') }}</div>
            <div class="mt-2 divide-y divide-slate-100 text-sm">
                @forelse ($topRefs as $r)
                    <div class="flex items-center justify-between gap-3 py-1.5">
                        <span class="truncate text-slate-700">{{ $r->referrer }}</span>
                        <span class="shrink-0 font-semibold">{{ $r->n }}</span>
                    </div>
                @empty
                    <div class="py-2 text-slate-500">{{ __('issues.an_none') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
