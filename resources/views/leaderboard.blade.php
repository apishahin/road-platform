@extends('layouts.app')

@section('title', __('issues.lb_title'))
@section('description', __('issues.lb_sub'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8" data-leaderboard>
    <h1 class="text-2xl font-bold sm:text-3xl">{{ __('issues.lb_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.lb_sub') }}</p>

    @if ($me)
        <div style="margin-top:16px;background:linear-gradient(135deg,#16a34a,#0d9488);border-radius:14px;padding:16px 18px;color:#fff;" data-leaderboard-me>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div>
                    <p style="font-size:12px;opacity:.85;">{{ __('issues.lb_you') }}</p>
                    <p style="font-size:20px;font-weight:800;">
                        {{ $me['rank'] ? __('issues.lb_rank', ['n' => \App\Support\IssuePresenter::num($me['rank'])]) : __('issues.lb_no_rank') }}
                        · {{ \App\Support\IssuePresenter::num($me['score']) }} {{ __('issues.lb_points') }}
                    </p>
                </div>
                <span style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:4px 12px;font-size:12px;font-weight:700;">{{ $me['badge'] }}</span>
            </div>
        </div>
    @endif

    <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
        @forelse ($rows as $r)
            <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0" @if ($r['me']) style="background:#f0fdf4;" @endif>
                <span style="width:30px;text-align:center;font-weight:800;color:{{ $r['rank'] <= 3 ? '#b45309' : '#94a3b8' }};">
                    {{ \App\Support\IssuePresenter::num($r['rank']) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-slate-900">
                        {{ $r['name'] }}@if ($r['me']) <span style="color:#16a34a;">({{ __('issues.lb_you_short') }})</span>@endif
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ __('issues.lb_breakdown', ['r' => \App\Support\IssuePresenter::num($r['reports']), 'o' => \App\Support\IssuePresenter::num($r['obs'])]) }}
                    </p>
                </div>
                <span style="background:#f1f5f9;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;color:#475569;white-space:nowrap;">{{ $r['badge'] }}</span>
                <span style="width:44px;text-align:right;font-weight:800;color:#0f172a;">{{ \App\Support\IssuePresenter::num($r['score']) }}</span>
            </div>
        @empty
            <div class="px-4 py-10 text-center text-sm text-slate-500">{{ __('issues.lb_empty') }}</div>
        @endforelse
    </div>

    <p class="mt-4 text-xs text-slate-400">{{ __('issues.lb_note') }}</p>
</div>
@endsection
