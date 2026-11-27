@extends('layouts.app')

@section('title', __('core.ai_title'))

@section('content')
@php
    $providers = $cfg['providers'];
    $field = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500';
    $customProviders = collect($providers)->filter(fn ($provider) => ($provider['preset'] ?? 'custom') === 'custom')->values();
    $presetProviders = collect($providers)->filter(fn ($provider) => ($provider['preset'] ?? 'custom') !== 'custom')->values();
@endphp
<div class="mx-auto max-w-5xl px-4 py-6 sm:py-8" data-ai-settings data-models-url="{{ route('admin.ai-settings.models') }}" data-csrf="{{ csrf_token() }}">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">{{ __('core.ai_title') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">{{ __('core.ai_manager_intro') }}</p>
        </div>
        <form method="POST" action="{{ route('admin.ai-settings.test-chain') }}">
            @csrf
            <button class="rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2.5 text-sm font-bold text-indigo-800 hover:bg-indigo-100">{{ __('core.ai_test_fallback_chain') }}</button>
        </form>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.ai-settings.save') }}" class="mt-6 space-y-5">
        @csrf
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-bold text-slate-900">{{ __('core.ai_enabled') }}</h2>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">{{ __('core.ai_privacy') }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $cfg['enabled']))> {{ __('core.ai_enabled') }}</label>
            </div>
            <details class="mt-4 text-sm text-slate-600">
                <summary class="cursor-pointer font-semibold text-slate-700">{{ __('core.ai_advanced_limits') }}</summary>
                <div class="mt-3 grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_global_daily_cap') }}
                        <input required type="number" min="1" max="100000" name="daily_limit" value="{{ old('daily_limit', $cfg['daily_limit']) }}" class="{{ $field }}">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_user_hour_cap') }}
                        <input required type="number" min="1" max="100" name="per_user_hour_limit" value="{{ old('per_user_hour_limit', $cfg['per_user_hour_limit']) }}" class="{{ $field }}">
                    </label>
                </div>
                <p class="mt-2 text-xs text-amber-700">{{ __('core.ai_limits_hint') }}</p>
            </details>
        </section>

        <section>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ __('core.ai_providers_heading') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('core.ai_choose_service_hint') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('core.ai_priority_hint') }}</span>
            </div>
            <div class="mt-3 grid gap-4 lg:grid-cols-2" data-provider-list data-next="{{ count($providers) }}">
                @foreach ($presetProviders as $index => $provider)
                    @include('admin._ai-provider-form', ['provider' => $provider, 'index' => $index, 'field' => $field])
                @endforeach
                @foreach ($customProviders as $customIndex => $provider)
                    @include('admin._ai-provider-form', ['provider' => $provider, 'index' => $presetProviders->count() + $customIndex, 'field' => $field])
                @endforeach
            </div>
        </section>

        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <summary class="cursor-pointer text-sm font-bold text-slate-700">{{ __('core.ai_custom_toggle') }}</summary>
            <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('core.ai_custom_hint') }}</p>
            <button type="button" data-add-provider class="mt-3 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">+ {{ __('core.ai_add_custom') }}</button>
        </details>

        <template id="ai-provider-template">
            @include('admin._ai-provider-form', ['provider' => ['id' => '', 'preset' => 'custom', 'label' => '', 'type' => 'openai_compatible', 'model' => '', 'default_model' => '', 'endpoint' => '', 'enabled' => false, 'priority' => 99, 'daily_limit' => 25, 'has_api_key' => false, 'last_test_status' => null, 'last_tested_at' => null, 'is_default' => false], 'index' => '__INDEX__', 'field' => $field])
        </template>

        <button class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700">{{ __('core.save') }}</button>
    </form>
</div>

<script>
(function () {
    var root = document.querySelector('[data-ai-settings]');
    if (!root) return;
    var list = root.querySelector('[data-provider-list]'), add = root.querySelector('[data-add-provider]'), tpl = root.querySelector('#ai-provider-template');
    if (add) add.addEventListener('click', function () {
        var i = Number(list.dataset.next || 0);
        if (list.querySelectorAll('[data-provider]').length >= 10) return;
        list.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__INDEX__', String(i)));
        list.dataset.next = String(i + 1);
    });
    list.addEventListener('click', function (e) {
        var remove = e.target.closest('[data-remove-provider]');
        if (remove) { remove.closest('[data-provider]').remove(); return; }
        var sync = e.target.closest('[data-sync-models]');
        if (!sync) return;
        var card = sync.closest('[data-provider]');
        var providerId = sync.dataset.providerId;
        if (!providerId) return;
        var message = card.querySelector('[data-model-message]');
        sync.disabled = true;
        if (message) message.textContent = @json(__('core.ai_models_loading'));
        fetch(root.dataset.modelsUrl, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf},
            body: JSON.stringify({provider_id: providerId})
        }).then(function (response) {
            return response.json().then(function (body) { if (!response.ok) throw new Error(body.message || ''); return body; });
        }).then(function (body) {
            var models = Array.isArray(body.data) ? body.data : [];
            var input = card.querySelector('[data-model-input]');
            var id = input && input.getAttribute('list');
            var datalist = id ? document.getElementById(id) : null;
            if (datalist) {
                datalist.innerHTML = '';
                models.forEach(function (model) { var option = document.createElement('option'); option.value = model; datalist.appendChild(option); });
            }
            if (message) message.textContent = models.length ? @json(__('core.ai_models_ready')) : @json(__('core.ai_models_empty'));
        }).catch(function (error) {
            if (message) message.textContent = error.message || @json(__('core.ai_models_failed'));
        }).finally(function () { sync.disabled = false; });
    });
    list.addEventListener('input', function (e) {
        if (!e.target.matches('[data-api-key]') || !e.target.value.trim()) return;
        var enabled = e.target.closest('[data-provider]').querySelector('[data-provider-enabled]');
        if (enabled) enabled.checked = true;
    });
})();
</script>
@endsection
