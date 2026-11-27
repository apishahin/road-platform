<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiReportSuggestionService;
use App\Services\AuditService;
use App\Support\AiProviderSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AiSettingsController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);
        return view('admin.ai-settings', ['cfg' => AiProviderSettings::forForm()]);
    }

    public function save(Request $request, AuditService $audit)
    {
        $this->guard($request);
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'per_user_hour_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'default_provider_index' => ['nullable', 'integer', 'min:0', 'max:9'],
            // Five template cards plus up to five legacy/custom rows may be
            // submitted. The settings class limits actual saved entries to 5.
            'providers' => ['nullable', 'array', 'max:10'],
            'providers.*.id' => ['nullable', 'string', 'max:32'],
            'providers.*.preset' => ['nullable', Rule::in([...array_keys(AiProviderSettings::presets()), 'custom'])],
            'providers.*.label' => ['nullable', 'string', 'max:80'],
            'providers.*.type' => ['nullable', Rule::in(AiProviderSettings::TYPES)],
            'providers.*.model' => ['nullable', 'string', 'max:160'],
            // Credentials and photos must never be sent over plaintext HTTP.
            'providers.*.endpoint' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'providers.*.api_key' => ['nullable', 'string', 'max:500'],
            'providers.*.clear_api_key' => ['nullable', 'boolean'],
            'providers.*.enabled' => ['nullable', 'boolean'],
            'providers.*.priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'providers.*.daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        try {
            AiProviderSettings::save($data, (array) ($data['providers'] ?? []));
        } catch (\LengthException) {
            throw ValidationException::withMessages(['providers' => __('core.ai_provider_limit')]);
        }
        $audit->log($request->user(), 'report_ai.settings_saved', 'settings', AiProviderSettings::KEY, [
            'enabled' => $request->boolean('enabled'),
            'provider_count' => count(array_filter((array) ($data['providers'] ?? []), fn ($p) => ! empty($p['id']) || trim((string) ($p['api_key'] ?? '')) !== '')),
        ]);

        return back()->with('status', __('core.ai_saved'));
    }

    /** One no-photo probe for an explicitly saved provider. */
    public function test(Request $request, AiReportSuggestionService $suggestions, AuditService $audit)
    {
        $this->guard($request);
        $data = $request->validate(['provider_id' => ['required', 'string', 'max:32']]);
        $provider = AiProviderSettings::providerForTest($data['provider_id']);
        if ($provider === null) {
            throw ValidationException::withMessages(['ai_test' => __('core.ai_test_unavailable')]);
        }

        try {
            $suggestions->probe($provider);
        } catch (\Throwable) {
            AiProviderSettings::recordTestResult($provider['id'], false);
            $audit->log($request->user(), 'report_ai.connection_failed', 'settings', $provider['id'], [
                'provider_id' => $provider['id'], 'model' => $provider['model'],
            ]);
            throw ValidationException::withMessages(['ai_test' => __('core.ai_test_failed')]);
        }

        AiProviderSettings::recordTestResult($provider['id'], true);
        $audit->log($request->user(), 'report_ai.connection_succeeded', 'settings', $provider['id'], [
            'provider_id' => $provider['id'], 'model' => $provider['model'],
        ]);

        return back()->with('status', __('core.ai_test_ok', ['provider' => $provider['label']]));
    }

    /** Return account-visible model IDs only; keys and citizen data stay server-side. */
    public function models(Request $request, AiReportSuggestionService $suggestions, AuditService $audit)
    {
        $this->guard($request);
        $data = $request->validate(['provider_id' => ['required', 'string', 'max:32']]);
        $provider = AiProviderSettings::providerForTest($data['provider_id']);
        if ($provider === null) {
            return response()->json(['message' => __('core.ai_models_unavailable')], 422);
        }

        try {
            $models = $suggestions->models($provider);
        } catch (\Throwable) {
            return response()->json(['message' => __('core.ai_models_failed')], 422);
        }

        $audit->log($request->user(), 'report_ai.models_synced', 'settings', $provider['id'], [
            'provider_id' => $provider['id'], 'count' => count($models),
        ]);

        return response()->json(['data' => $models]);
    }

    /** Probe every enabled provider in the same priority order as live fallback. */
    public function testChain(Request $request, AiReportSuggestionService $suggestions, AuditService $audit)
    {
        $this->guard($request);
        $providers = AiProviderSettings::enabledProvidersForTest();
        if ($providers === []) {
            throw ValidationException::withMessages(['ai_test' => __('core.ai_chain_empty')]);
        }

        $successes = 0;
        foreach ($providers as $provider) {
            try {
                $suggestions->probe($provider);
                $successes++;
                AiProviderSettings::recordTestResult($provider['id'], true);
                $event = 'report_ai.chain_connection_succeeded';
            } catch (\Throwable) {
                AiProviderSettings::recordTestResult($provider['id'], false);
                $event = 'report_ai.chain_connection_failed';
            }
            $audit->log($request->user(), $event, 'settings', $provider['id'], [
                'provider_id' => $provider['id'], 'model' => $provider['model'],
            ]);
        }

        if ($successes === 0) {
            throw ValidationException::withMessages(['ai_test' => __('core.ai_chain_failed')]);
        }

        return back()->with('status', __('core.ai_chain_tested', ['success' => $successes, 'total' => count($providers)]));
    }
}
