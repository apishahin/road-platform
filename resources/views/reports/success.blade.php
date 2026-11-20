@extends('layouts.app')

@section('title', __('report.success_title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-12 text-center">
    <div class="inline-flex size-16 items-center justify-center rounded-full bg-green-100 text-2xl text-green-600">✓</div>
    <h1 class="mt-4 text-2xl font-bold text-slate-900">{{ __('report.submitted') }}</h1>
    <p class="mt-2 text-sm text-slate-600">{{ __('report.moderation_notice') }}</p>
    <p class="mt-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800" data-autofollow-note>{{ __('report.autofollow_notice') }}</p>

    <div class="mt-6 flex flex-col gap-2">
        <a href="{{ route('issues.index') }}"
           class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('report.view_issues') }}</a>
        <a href="{{ route('home') }}"
           class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Home') }}</a>
    </div>
</div>
@endsection