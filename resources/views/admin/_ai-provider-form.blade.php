@php
    $isPreset = ($provider['preset'] ?? 'custom') !== 'custom';
    $status = $provider['last_test_status'] ?? null;
    $modelsListId = 'ai-models-' . $index;
@endphp
<section data-provider class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <input type="hidden" name="providers[{{ $index }}][id]" value="{{ $provider['id'] }}">
    <input type="hidden" name="providers[{{ $index }}][preset]" value="{{ $provider['preset'] ?? 'custom' }}">
    @if ($isPreset)
        {{-- Presets own protocol and endpoint. The selected model may change. --}}
        <input type="hidden" name="providers[{{ $index }}][label]" value="{{ $provider['label'] }}">
        <input type="hidden" name="providers[{{ $index }}][type]" value="{{ $provider['type'] }}">
        <input type="hidden" name="providers[{{ $index }}][endpoint]" value="{{ $provider['endpoint'] }}">
    @endif

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="font-bold text-slate-900">{{ $provider['label'] ?: __('core.ai_custom_provider') }}</h3>
            @if ($isPreset)
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('core.ai_preset_' . $provider['preset'] . '_hint') }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($status === 'success')
                <span class="rounded-full bg-green-100 px-2.5 py-1 text-[11px] font-bold text-green-800">✓ {{ __('core.ai_test_status_success') }}</span>
            @elseif ($status === 'failed')
                <span class="rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-bold text-red-800">! {{ __('core.ai_test_status_failed') }}</span>
            @else
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ __('core.ai_test_status_unknown') }}</span>
            @endif
            @unless ($isPreset)
                <button type="button" data-remove-provider class="text-xs font-bold text-red-700 underline">{{ __('core.ai_provider_remove') }}</button>
            @endunless
        </div>
    </div>

    @unless ($isPreset)
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_provider_name') }}
                <input name="providers[{{ $index }}][label]" value="{{ $provider['label'] }}" maxlength="80" class="{{ $field }}">
            </label>
            <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_provider_type') }}
                <select name="providers[{{ $index }}][type]" class="{{ $field }}">
                    <option value="openai_compatible" @selected($provider['type'] === 'openai_compatible')>{{ __('core.ai_protocol_openai') }}</option>
                    <option value="openai_responses" @selected($provider['type'] === 'openai_responses')>{{ __('core.ai_protocol_openai_responses') }}</option>
                    <option value="gemini" @selected($provider['type'] === 'gemini')>{{ __('core.ai_protocol_gemini') }}</option>
                    <option value="anthropic" @selected($provider['type'] === 'anthropic')>{{ __('core.ai_protocol_anthropic') }}</option>
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">{{ __('core.ai_endpoint') }}
                <input type="url" name="providers[{{ $index }}][endpoint]" value="{{ $provider['endpoint'] }}" maxlength="500" placeholder="https://…" class="{{ $field }}">
                <span class="mt-1 block text-[11px] font-normal text-slate-500">{{ __('core.ai_endpoint_hint') }}</span>
            </label>
        </div>
    @endunless

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_key') }}
            <input data-api-key type="password" autocomplete="new-password" name="providers[{{ $index }}][api_key]" maxlength="500" class="{{ $field }}" placeholder="{{ __('core.ai_key_paste') }}">
            <span class="mt-1 block text-[11px] font-normal text-slate-500">{{ $provider['has_api_key'] ? __('core.ai_key_keep') : __('core.ai_key_not_saved') }}</span>
        </label>
        <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_model') }}
            <input data-model-input list="{{ $modelsListId }}" name="providers[{{ $index }}][model]" value="{{ $provider['model'] }}" maxlength="160" class="{{ $field }}" placeholder="{{ $provider['default_model'] ?: __('core.ai_model') }}">
            <datalist id="{{ $modelsListId }}"><option value="{{ $provider['model'] }}"></datalist>
            @if ($provider['default_model'] ?? false)
                <span class="mt-1 block text-[11px] font-normal text-slate-500">{{ __('core.ai_default_model') }}: {{ $provider['default_model'] }}</span>
            @endif
        </label>
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-3 sm:items-end">
        <div class="space-y-2 pb-2">
            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input data-provider-enabled type="checkbox" name="providers[{{ $index }}][enabled]" value="1" @checked($provider['enabled'])> {{ __('core.ai_provider_enabled') }}</label>
            <label class="flex items-center gap-2 text-xs font-semibold text-indigo-800"><input type="radio" name="default_provider_index" value="{{ $index }}" @checked($provider['is_default'] ?? false)> {{ __('core.ai_default_provider') }}</label>
        </div>
        <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_priority') }}
            <input type="number" min="0" max="1000" name="providers[{{ $index }}][priority]" value="{{ $provider['priority'] }}" class="{{ $field }}">
        </label>
        <label class="text-sm font-semibold text-slate-700">{{ __('core.ai_provider_daily_cap') }}
            <input type="number" min="1" max="100000" name="providers[{{ $index }}][daily_limit]" value="{{ $provider['daily_limit'] }}" class="{{ $field }}">
        </label>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @if ($provider['has_api_key'] && $provider['id'] !== '')
            <button type="button" data-sync-models data-provider-id="{{ $provider['id'] }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">{{ __('core.ai_sync_models') }}</button>
            <button type="submit" formmethod="POST" formaction="{{ route('admin.ai-settings.test') }}" name="provider_id" value="{{ $provider['id'] }}" class="rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-800 hover:bg-indigo-100">{{ __('core.ai_test_connection') }}</button>
        @endif
        <span data-model-message class="text-xs text-slate-500">{{ $provider['has_api_key'] ? __('core.ai_models_save_then_test') : __('core.ai_models_save_key_first') }}</span>
    </div>

    @if ($provider['has_api_key'])
        <label class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-red-700"><input type="checkbox" name="providers[{{ $index }}][clear_api_key]" value="1"> {{ __('core.ai_key_clear') }}</label>
    @endif
</section>
