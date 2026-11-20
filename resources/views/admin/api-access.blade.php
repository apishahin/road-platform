@extends('layouts.app')

@section('title', __('issues.api_menu'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold">{{ __('issues.api_menu') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.api_intro') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    @if (session('new_api_key'))
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4" data-new-key>
            <div class="text-sm font-bold text-amber-900">{{ __('issues.api_key_once') }}</div>
            <div class="mt-2 flex items-center gap-2">
                <code class="flex-1 select-all break-all rounded-lg bg-white px-3 py-2 text-sm font-mono border border-amber-200">{{ session('new_api_key') }}</code>
                <button type="button" onclick="navigator.clipboard.writeText('{{ session('new_api_key') }}')" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white">{{ __('issues.api_copy') }}</button>
            </div>
        </div>
    @endif

    {{-- Public read toggle --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('issues.api_public_title') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('issues.api_public_hint') }}</p>
        <form method="POST" action="{{ route('admin.api.toggle') }}" class="mt-3 flex items-center gap-3">
            @csrf
            <input type="hidden" name="public_read" value="{{ $cfg['public_read'] ? '0' : '1' }}">
            <span class="text-sm font-semibold {{ $cfg['public_read'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $cfg['public_read'] ? __('issues.api_public_on') : __('issues.api_public_off') }}
            </span>
            <button class="rounded-lg border border-slate-300 px-4 py-1.5 text-sm font-semibold hover:bg-slate-50">
                {{ $cfg['public_read'] ? __('issues.api_turn_off') : __('issues.api_turn_on') }}
            </button>
        </form>
    </div>

    {{-- Create key --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('issues.api_keys_title') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('issues.api_keys_hint') }}</p>
        <form method="POST" action="{{ route('admin.api.keys.store') }}" class="mt-3 flex gap-2">
            @csrf
            <input name="label" required maxlength="80" placeholder="{{ __('issues.api_label_ph') }}"
                   class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.api_create') }}</button>
        </form>

        @if ($cfg['keys'])
            <div class="mt-4 divide-y divide-slate-100">
                @foreach ($cfg['keys'] as $k)
                    <div class="flex items-center gap-3 py-3 text-sm">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $k['label'] }} <code class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $k['prefix'] }}…</code></div>
                            <div class="text-xs text-slate-500">
                                {{ __('issues.api_key_meta', ['created' => $k['created_at'], 'used' => $k['last_used_at'] ?: __('issues.sys_na'), 'n' => $k['requests']]) }}
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.api.keys.destroy', $k['id']) }}" onsubmit="return confirm('{{ __('issues.api_revoke_confirm') }}')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">{{ __('issues.api_revoke') }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">{{ __('issues.api_no_keys') }}</p>
        @endif
    </div>

    {{-- Docs pointer --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 text-sm">
        📖 <a href="{{ route('developers') }}" target="_blank" class="font-semibold text-brand-700 underline">{{ __('issues.api_docs_link') }}</a>
        <span class="text-slate-500">— {{ __('issues.api_docs_hint') }}</span>
    </div>
</div>
@endsection
