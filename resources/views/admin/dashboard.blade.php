@extends('layouts.app')

@section('title', __('admindash.title'))

@section('content')
@php
    $cards = [
        ['key' => 'reports_pending',      'label' => 'admindash.card_reports_pending',      'url' => route('admin.work-center', ['tab' => 'moderation']),   'accent' => 'amber',   'warm' => true],
        ['key' => 'applications_pending', 'label' => 'admindash.card_applications_pending', 'url' => route('admin.applications.index'),                     'accent' => 'amber',   'warm' => true],
        ['key' => 'users_unverified',     'label' => 'admindash.card_users_unverified',     'url' => route('admin.users.index', ['v' => 'unverified']),       'accent' => 'amber',   'warm' => true],
        ['key' => 'users_nophone',        'label' => 'admindash.card_users_nophone',        'url' => route('admin.users.index', ['v' => 'none']),             'accent' => 'slate',   'warm' => true],
        ['key' => 'blocked_ips',          'label' => 'admindash.card_blocked_ips',          'url' => route('admin.users.index'),                              'accent' => 'rose',    'warm' => false],
        ['key' => 'users_total',          'label' => 'admindash.card_users_total',          'url' => route('admin.users.index'),                              'accent' => 'brand',   'warm' => false],
        ['key' => 'issues_week',          'label' => 'admindash.card_issues_week',          'url' => route('admin.work-center', ['tab' => 'issues']),       'accent' => 'emerald', 'warm' => false],
    ];
    $accents = [
        'amber'   => ['ring' => 'ring-amber-200',   'bg' => 'bg-amber-50',   'text' => 'text-amber-700',   'hot' => true],
        'rose'    => ['ring' => 'ring-rose-200',    'bg' => 'bg-rose-50',    'text' => 'text-rose-700',    'hot' => true],
        'slate'   => ['ring' => 'ring-slate-200',   'bg' => 'bg-slate-100',  'text' => 'text-slate-600',   'hot' => false],
        'brand'   => ['ring' => 'ring-brand-200',   'bg' => 'bg-brand-50',   'text' => 'text-brand-700',   'hot' => false],
        'emerald' => ['ring' => 'ring-emerald-200', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'hot' => false],
    ];
@endphp
<div class="mx-auto max-w-5xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('admindash.title') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('admindash.subtitle') }}</p>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($cards as $c)
            @php
                $n = $stats[$c['key']];
                $a = $accents[$c['accent']];
                $needsAttention = $c['warm'] && $n > 0;
            @endphp
            <a href="{{ $c['url'] }}"
               class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md {{ $needsAttention ? 'ring-2 ' . $a['ring'] : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-3xl font-extrabold tracking-tight {{ $needsAttention ? $a['text'] : 'text-slate-900' }}">{{ $n }}</p>
                    @if($needsAttention)
                        <span class="mt-1 inline-block size-2.5 rounded-full {{ str_replace('text-', 'bg-', $a['text']) }} animate-pulse"></span>
                    @endif
                </div>
                <p class="mt-2 text-sm font-semibold text-slate-600">{{ __($c['label']) }}</p>
                <p class="mt-3 text-xs font-bold text-brand-700 opacity-0 transition-opacity group-hover:opacity-100">{{ __('admindash.open') }} →</p>
            </a>
        @endforeach
    </div>
</div>
@endsection
