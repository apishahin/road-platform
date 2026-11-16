@extends('layouts.app')

@section('title', __('pwa.offline_title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-16 text-center">
    <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-slate-100 text-slate-400">
        <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M9.2 5.4A12.9 12.9 0 0 1 12 5c3.9 0 7.4 1.6 10 4.2M5.7 9.1C4.7 9.8 3.8 10.6 3 11.5m4 4.3a7.5 7.5 0 0 1 5-1.9c1.1 0 2.2.25 3.2.7M8.5 18.6a3.5 3.5 0 0 1 7 0M12 21h.01"/>
        </svg>
    </div>
    <h1 class="mt-5 text-xl font-bold text-slate-900">{{ __('pwa.offline_title') }}</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ __('pwa.offline_body') }}</p>
    <button type="button" onclick="window.location.reload()"
            class="mt-6 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
        {{ __('pwa.retry') }}
    </button>
</div>
@endsection
