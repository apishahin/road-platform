@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8" data-analytics>
    <h1 class="text-2xl font-bold">{{ __('issues.an_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.an_privacy') }}</p>

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
            <div class="text-2xl font-bold text-slate-800">{{ $total30 }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ __('issues.an_total30') }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $avg30 }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ __('issues.an_avg') }}</div>
        </div>
    </div>

    {{-- 30-day chart --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-an-chart>
        <div class="font-semibold">{{ __('issues.an_chart') }}</div>
        <div class="mt-4 flex items-end gap-[3px]" style="height:140px;">
            @foreach ($days as $d)
                <div class="flex min-w-0 flex-1 flex-col items-center justify-end" title="{{ $d['label'] }}: {{ $d['views'] }} {{ __('issues.an_views') }}, {{ $d['uniques'] }} {{ __('issues.an_uniques') }}">
                    <div class="w-full rounded-t"
                         style="height:{{ max(2, round($d['views'] / $max * 120)) }}px;background:{{ $d['date'] === now()->toDateString() ? '#059669' : '#a7f3d0' }};"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-[10px] text-slate-400">
            <span>{{ $days[0]['label'] }}</span><span>{{ $days[15]['label'] }}</span><span>{{ $days[29]['label'] }}</span>
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
