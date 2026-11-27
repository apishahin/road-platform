<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Encrypted, administrator-managed configuration for optional report-photo AI.
 * Provider protocol/endpoint presets are server-owned; a saved model override
 * lets an administrator choose an account-visible vision model after syncing.
 */
class AiProviderSettings
{
    public const KEY = 'report_ai_providers';
    public const TYPES = ['openai_compatible', 'openai_responses', 'gemini', 'anthropic'];
    public const MAX_PROVIDERS = 5;

    /**
     * Ready provider cards. Free-tier availability, model access, and quotas
     * are account/provider dependent; every card is tested before citizen use.
     */
    public static function presets(): array
    {
        return [
            'gemini_flash' => [
                'label' => 'Google Gemini 3.8 Flash',
                'type' => 'gemini',
                'model' => 'gemini-3.8-flash',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
                'daily_limit' => 100,
            ],
            'groq_vision' => [
                'label' => 'Groq Qwen Vision',
                'type' => 'openai_compatible',
                'model' => 'qwen/qwen3.8-27b',
                'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
                'daily_limit' => 100,
            ],
            'openrouter_vision' => [
                'label' => 'OpenRouter vision',
                'type' => 'openai_compatible',
                'model' => 'inclusionai/ling-3.2-flash:free',
                'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
                'daily_limit' => 50,
            ],
            'openai_mini' => [
                'label' => 'OpenAI GPT-6.1 Terra',
                'type' => 'openai_responses',
                'model' => 'gpt-6.1-terra',
                'endpoint' => 'https://api.openai.com/v1/responses',
                'daily_limit' => 100,
            ],
            'anthropic_haiku' => [
                'label' => 'Anthropic Claude Haiku 4.5',
                'type' => 'anthropic',
                'model' => 'claude-haiku-4-5',
                'endpoint' => 'https://api.anthropic.com/v1/messages',
                'daily_limit' => 100,
            ],
        ];
    }

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'daily_limit' => 100,
            'per_user_hour_limit' => 3,
            'providers' => [],
        ];
    }

    private static function raw(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
            $decoded = json_decode((string) $raw, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function presetKey(array $provider): ?string
    {
        $key = (string) ($provider['preset'] ?? '');
        if (array_key_exists($key, self::presets())) {
            return $key;
        }

        // Settings saved before templates existed are recognized where possible.
        foreach (self::presets() as $candidate => $preset) {
            if (($provider['type'] ?? null) === $preset['type']
                && rtrim((string) ($provider['endpoint'] ?? ''), '/') === rtrim($preset['endpoint'], '/')
                && ($provider['model'] ?? null) === $preset['model']) {
                return $candidate;
            }
        }

        return null;
    }

    private static function modelFor(array $provider, ?array $preset = null): string
    {
        if ($preset !== null) {
            $override = trim((string) ($provider['model_override'] ?? ''));
            return $override !== '' ? $override : $preset['model'];
        }

        return trim((string) ($provider['model'] ?? ''));
    }

    private static function testMetadata(array $provider): array
    {
        $status = (string) ($provider['last_test_status'] ?? '');

        return [
            'last_test_status' => in_array($status, ['success', 'failed'], true) ? $status : null,
            'last_tested_at' => isset($provider['last_tested_at']) ? (string) $provider['last_tested_at'] : null,
        ];
    }

    private static function presetFormRow(string $key, array $stored = []): array
    {
        $preset = self::presets()[$key];

        return [
            'id' => (string) ($stored['id'] ?? ''),
            'preset' => $key,
            'label' => $preset['label'],
            'type' => $preset['type'],
            'model' => self::modelFor($stored, $preset),
            'default_model' => $preset['model'],
            'endpoint' => $preset['endpoint'],
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'priority' => max(0, (int) ($stored['priority'] ?? array_search($key, array_keys(self::presets()), true))),
            'daily_limit' => max(1, (int) ($stored['daily_limit'] ?? $preset['daily_limit'])),
            'has_api_key' => ! empty($stored['api_key']),
            ...self::testMetadata($stored),
        ];
    }

    /** Configuration safe for the admin form; encrypted keys never leave PHP. */
    public static function forForm(): array
    {
        $raw = array_replace_recursive(self::defaults(), self::raw());
        $savedByPreset = [];
        $custom = [];

        foreach ((array) ($raw['providers'] ?? []) as $provider) {
            if (! is_array($provider) || empty($provider['id'])) {
                continue;
            }
            $presetKey = self::presetKey($provider);
            if ($presetKey !== null && ! isset($savedByPreset[$presetKey])) {
                $savedByPreset[$presetKey] = $provider;
                continue;
            }
            $custom[] = [
                'id' => (string) $provider['id'],
                'preset' => 'custom',
                'label' => (string) ($provider['label'] ?? ''),
                'type' => in_array(($provider['type'] ?? ''), self::TYPES, true) ? $provider['type'] : 'openai_compatible',
                'model' => self::modelFor($provider),
                'default_model' => '',
                'endpoint' => (string) ($provider['endpoint'] ?? ''),
                'enabled' => (bool) ($provider['enabled'] ?? false),
                'is_default' => (bool) ($provider['is_default'] ?? false),
                'priority' => max(0, (int) ($provider['priority'] ?? 0)),
                'daily_limit' => max(1, (int) ($provider['daily_limit'] ?? 25)),
                'has_api_key' => ! empty($provider['api_key']),
                ...self::testMetadata($provider),
            ];
        }

        $providers = [];
        foreach (array_keys(self::presets()) as $key) {
            $providers[] = self::presetFormRow($key, $savedByPreset[$key] ?? []);
        }
        usort($custom, fn ($a, $b) => [$a['priority'], $a['label']] <=> [$b['priority'], $b['label']]);

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'daily_limit' => max(1, (int) ($raw['daily_limit'] ?? 100)),
            'per_user_hour_limit' => max(1, (int) ($raw['per_user_hour_limit'] ?? 3)),
            'providers' => [...$providers, ...$custom],
        ];
    }

    /** Server-only configured providers with decrypted keys for citizen drafts. */
    public static function usableProviders(): array
    {
        return self::configuredProviders(requireFeatureEnabled: true, requireProviderEnabled: true);
    }

    /** A saved provider can be tested before global/citizen feature enablement. */
    public static function providerForTest(string $id): ?array
    {
        foreach (self::configuredProviders(requireFeatureEnabled: false, requireProviderEnabled: false) as $provider) {
            if (hash_equals($provider['id'], $id)) {
                return $provider;
            }
        }

        return null;
    }

    /** Enabled providers in their real fallback order for an admin chain test. */
    public static function enabledProvidersForTest(): array
    {
        return self::configuredProviders(requireFeatureEnabled: false, requireProviderEnabled: true);
    }

    private static function configuredProviders(bool $requireFeatureEnabled, bool $requireProviderEnabled): array
    {
        $raw = array_replace_recursive(self::defaults(), self::raw());
        if ($requireFeatureEnabled && empty($raw['enabled'])) {
            return [];
        }

        $providers = [];
        foreach ((array) ($raw['providers'] ?? []) as $provider) {
            if (! is_array($provider) || empty($provider['id']) || ($requireProviderEnabled && empty($provider['enabled']))) {
                continue;
            }

            $presetKey = self::presetKey($provider);
            $preset = $presetKey !== null ? (self::presets()[$presetKey] ?? null) : null;
            $type = $preset['type'] ?? ($provider['type'] ?? '');
            if (! in_array($type, self::TYPES, true)) {
                continue;
            }
            try {
                $key = isset($provider['api_key']) ? Crypt::decryptString((string) $provider['api_key']) : '';
            } catch (\Throwable) {
                $key = '';
            }
            $model = self::modelFor($provider, $preset);
            $endpoint = rtrim(trim((string) ($preset['endpoint'] ?? $provider['endpoint'] ?? '')), '/');
            if ($key === '' || $model === '' || $endpoint === '' || ! str_starts_with(strtolower($endpoint), 'https://')) {
                continue;
            }
            $providers[] = [
                'id' => (string) $provider['id'],
                'label' => (string) ($preset['label'] ?? $provider['label'] ?? 'AI provider'),
                'type' => $type,
                'model' => $model,
                'endpoint' => $endpoint,
                'api_key' => $key,
                'priority' => max(0, (int) ($provider['priority'] ?? 0)),
                'daily_limit' => max(1, (int) ($provider['daily_limit'] ?? ($preset['daily_limit'] ?? 25))),
            ];
        }
        usort($providers, fn ($a, $b) => [$a['priority'], $a['label']] <=> [$b['priority'], $b['label']]);

        return $providers;
    }

    /** Store a safe connection-test state for the provider card, without keys. */
    public static function recordTestResult(string $id, bool $success): void
    {
        $raw = self::raw();
        $changed = false;
        foreach ((array) ($raw['providers'] ?? []) as $index => $provider) {
            if (! is_array($provider) || ! isset($provider['id']) || ! hash_equals((string) $provider['id'], $id)) {
                continue;
            }
            $raw['providers'][$index]['last_test_status'] = $success ? 'success' : 'failed';
            $raw['providers'][$index]['last_tested_at'] = now()->toIso8601String();
            $changed = true;
            break;
        }
        if ($changed) {
            DB::table('settings')->updateOrInsert(
                ['key' => self::KEY],
                ['value' => json_encode(array_replace_recursive(self::defaults(), $raw)), 'updated_at' => now()]
            );
        }
    }

    public static function limits(): array
    {
        $raw = array_replace_recursive(self::defaults(), self::raw());

        return [
            'daily_limit' => max(1, (int) $raw['daily_limit']),
            'per_user_hour_limit' => max(1, (int) $raw['per_user_hour_limit']),
        ];
    }

    public static function available(): bool
    {
        return static::usableProviders() !== [];
    }

    /**
     * Save normalized providers. A preset owns its protocol/endpoint, while a
     * selected account-visible model is retained as a safe model override.
     */
    public static function save(array $settings, array $providerInputs): void
    {
        $old = self::raw();
        $oldById = collect((array) ($old['providers'] ?? []))->keyBy('id');
        $providers = [];

        $requestedDefault = isset($settings['default_provider_index']) ? (string) $settings['default_provider_index'] : null;
        foreach ($providerInputs as $inputIndex => $input) {
            $presetKey = (string) ($input['preset'] ?? 'custom');
            $preset = self::presets()[$presetKey] ?? null;
            $id = (string) ($input['id'] ?? '');
            $oldProvider = $oldById->get($id, []);
            if ($id === '' || ! is_array($oldProvider)) {
                $id = (string) Str::ulid();
                $oldProvider = [];
            }

            $plainKey = trim((string) ($input['api_key'] ?? ''));
            $clearKey = ! empty($input['clear_api_key']) && $plainKey === '';
            $existingEncryptedKey = (string) ($oldProvider['api_key'] ?? '');
            $encryptedKey = $plainKey !== '' ? Crypt::encryptString($plainKey) : ($clearKey ? '' : $existingEncryptedKey);
            if ($clearKey) {
                continue;
            }
            // Do not turn an unused blank template/custom card into a saved
            // provider slot. Existing encrypted keys remain intact.
            if ($plainKey === '' && $encryptedKey === '') {
                continue;
            }

            $label = trim((string) ($preset['label'] ?? $input['label'] ?? ''));
            $requestedModel = trim((string) ($input['model'] ?? ''));
            $model = $preset !== null ? ($requestedModel !== '' ? $requestedModel : $preset['model']) : $requestedModel;
            $endpoint = rtrim(trim((string) ($preset['endpoint'] ?? $input['endpoint'] ?? '')), '/');
            if ($label === '' && $model === '' && $endpoint === '') {
                continue;
            }
            $model = mb_substr($model, 0, 160);
            $modelOverride = $preset !== null && $model !== $preset['model'] ? $model : null;

            $providers[] = [
                'id' => $id,
                'preset' => $presetKey,
                'label' => mb_substr($label, 0, 80),
                'type' => $preset['type'] ?? (in_array(($input['type'] ?? ''), self::TYPES, true) ? $input['type'] : 'openai_compatible'),
                'model' => $model,
                'model_override' => $modelOverride,
                'endpoint' => mb_substr($endpoint, 0, 500),
                'api_key' => $encryptedKey,
                'enabled' => ! empty($input['enabled']) && $encryptedKey !== '',
                'is_default' => $requestedDefault !== null && $requestedDefault === (string) $inputIndex,
                'priority' => max(0, min(1000, (int) ($input['priority'] ?? 0))),
                'daily_limit' => max(1, min(100000, (int) ($input['daily_limit'] ?? ($preset['daily_limit'] ?? 25)))),
                // Preserve only non-secret, useful card status across edits.
                'last_test_status' => $oldProvider['last_test_status'] ?? null,
                'last_tested_at' => $oldProvider['last_tested_at'] ?? null,
            ];
        }

        // A radio selection is normally unique. Defend against crafted input
        // and retain a deterministic default even if the selected card lacks a
        // usable key: the lowest-priority enabled saved provider becomes it.
        $defaultFound = false;
        foreach ($providers as &$provider) {
            $provider['is_default'] = ! empty($provider['is_default']) && ! empty($provider['enabled']) && ! $defaultFound;
            $defaultFound = $defaultFound || $provider['is_default'];
        }
        unset($provider);
        if (! $defaultFound) {
            $candidate = null;
            foreach ($providers as $index => $provider) {
                if (empty($provider['enabled']) || ($candidate !== null && $providers[$candidate]['priority'] <= $provider['priority'])) {
                    continue;
                }
                $candidate = $index;
            }
            if ($candidate !== null) {
                $providers[$candidate]['is_default'] = true;
            }
        }

        if (count($providers) > self::MAX_PROVIDERS) {
            throw new \LengthException('At most five AI providers can be configured.');
        }

        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode([
                'enabled' => ! empty($settings['enabled']),
                'daily_limit' => max(1, min(100000, (int) ($settings['daily_limit'] ?? 100))),
                'per_user_hour_limit' => max(1, min(100, (int) ($settings['per_user_hour_limit'] ?? 3))),
                'providers' => $providers,
            ]), 'updated_at' => now()]
        );
    }
}
