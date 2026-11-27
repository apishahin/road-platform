<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Support\AiProviderSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Optional, user-triggered report-draft assistant.
 *
 * This service deliberately receives no report location, account data, or
 * free-form citizen text. It sends one normalized image and a constrained leaf
 * category list to a configured provider, then returns a strictly validated
 * editable draft. It never persists a photo or changes an issue.
 */
class AiReportSuggestionService
{
    public function suggest(User $user, UploadedFile $photo): array
    {
        $providers = AiProviderSettings::usableProviders();
        if ($providers === []) {
            throw new \RuntimeException(__('report.ai_unavailable'));
        }

        $this->reserveGlobalAndUserQuota($user);
        $image = $this->normalizedImage($photo);
        $categories = Category::query()->reportable()->orderBy('sort')->get();
        if ($categories->isEmpty()) {
            throw new \RuntimeException(__('report.ai_unavailable'));
        }
        $allowed = $categories->map(fn (Category $category) => [
            'id' => $category->id,
            'bn' => $category->name_bn,
            'en' => $category->name_en,
        ])->values()->all();
        $prompt = $this->prompt($allowed);

        foreach ($providers as $provider) {
            if (! $this->reserveProviderQuota($provider)) {
                continue;
            }
            try {
                $content = $this->requestProvider($provider, $prompt, $image);
                $draft = $this->validateDraft($content, $categories->keyBy('id')->all());
                $draft['provider_id'] = $provider['id'];
                $draft['model'] = $provider['model'];
                return $draft;
            } catch (\RuntimeException) {
                // A correctly parsed but unusable answer must not be applied.
                // Do not report raw provider exceptions: some HTTP clients may
                // include a request URL, and Gemini credentials are query
                // parameters in its standard REST API.
                logger()->warning('AI report draft failed', ['provider_id' => $provider['id']]);
            } catch (\Throwable) {
                logger()->warning('AI report draft failed', ['provider_id' => $provider['id']]);
            }
        }

        throw new \RuntimeException(__('report.ai_unavailable'));
    }

    private function reserveGlobalAndUserQuota(User $user): void
    {
        $limits = AiProviderSettings::limits();
        $day = now()->format('Ymd');
        $untilTomorrow = max(60, now()->diffInSeconds(now()->addDay()->startOfDay()));
        $global = 'report-ai:global:' . $day;
        $userKey = 'report-ai:user:' . $user->id;

        if (RateLimiter::tooManyAttempts($global, $limits['daily_limit'])
            || RateLimiter::tooManyAttempts($userKey, $limits['per_user_hour_limit'])) {
            throw new \RuntimeException(__('report.ai_limit'), 429);
        }
        RateLimiter::hit($global, $untilTomorrow);
        RateLimiter::hit($userKey, 3600);
    }

    private function reserveProviderQuota(array $provider): bool
    {
        $key = 'report-ai:provider:' . $provider['id'] . ':' . now()->format('Ymd');
        if (RateLimiter::tooManyAttempts($key, $provider['daily_limit'])) {
            return false;
        }
        $seconds = max(60, now()->diffInSeconds(now()->addDay()->startOfDay()));
        RateLimiter::hit($key, $seconds);
        return true;
    }

    /** Re-encode to prevent EXIF/location transfer and bound provider bytes. */
    private function normalizedImage(UploadedFile $photo): string
    {
        if (! $photo->isValid() || $photo->getSize() > 10 * 1024 * 1024) {
            throw new \RuntimeException(__('report.ai_unavailable'));
        }
        $size = @getimagesize($photo->getRealPath());
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 40_000_000) {
            throw new \RuntimeException(__('report.ai_unavailable'));
        }

        try {
            $manager = new ImageManager(new GdDriver());
            $image = method_exists($manager, 'decode') ? $manager->decode($photo->getRealPath()) : $manager->read($photo->getRealPath());
            $image->orient()->scaleDown(width: 1280, height: 1280);
            return base64_encode((string) $image->encode(new JpegEncoder(quality: 82)));
        } catch (\Throwable $e) {
            report($e);
            throw new \RuntimeException(__('report.ai_unavailable'));
        }
    }

    private function prompt(array $categories): string
    {
        return 'You assist a Bangladesh civic road-reporting form. Analyze only visible road or safe-mobility conditions in the image. '
            . 'Do not identify people, number plates, private property, emergencies, responsible authorities, or repair status. '
            . 'Return one JSON object only, without markdown. Required keys: category_id, title, description, confidence, uncertainty. '
            . 'category_id must be one of the supplied numeric IDs. title must be a short Bangla title (max 150 characters). '
            . 'description must be concise Bangla, no more than 150 words, and say only what is reasonably visible. '
            . 'confidence is an integer from 0 to 100. uncertainty is a short Bangla note; use it when the image is unclear. '
            . 'If no category is reliable, choose the Other category only if it appears in the list and set confidence low. '
            . 'Allowed categories: ' . json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Explicit admin-only connection probe; it sends no citizen data or image. */
    public function probe(array $provider): void
    {
        $content = $this->requestProvider(
            $provider,
            'Return exactly this JSON object and nothing else: {"ok":true}',
            null,
        );
        if (trim($content) === '') {
            throw new \RuntimeException('AI provider returned no test response.');
        }
    }

    private function requestProvider(array $provider, string $prompt, ?string $image): string
    {
        return match ($provider['type']) {
            'gemini' => $this->gemini($provider, $prompt, $image),
            'anthropic' => $this->anthropic($provider, $prompt, $image),
            'openai_responses' => $this->openAiResponses($provider, $prompt, $image),
            default => $this->openAiCompatible($provider, $prompt, $image),
        };
    }

    private function openAiCompatible(array $provider, string $prompt, ?string $image): string
    {
        $content = [['type' => 'text', 'text' => $prompt]];
        if ($image !== null) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $image]];
        }
        $response = Http::acceptJson()->withToken($provider['api_key'])->timeout(18)->connectTimeout(5)
            ->post($provider['endpoint'], [
                'model' => $provider['model'],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('AI provider request failed.');
        }
        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content)) {
            throw new \RuntimeException('AI provider returned no draft.');
        }

        return $content;
    }

    /** OpenAI's current Responses API, kept separate from generic chat-compatible APIs. */
    private function openAiResponses(array $provider, string $prompt, ?string $image): string
    {
        $content = [['type' => 'input_text', 'text' => $prompt]];
        if ($image !== null) {
            $content[] = ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,' . $image];
        }
        $response = Http::acceptJson()->withToken($provider['api_key'])->timeout(18)->connectTimeout(5)
            ->post($provider['endpoint'], [
                'model' => $provider['model'],
                'input' => [['role' => 'user', 'content' => $content]],
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('AI provider request failed.');
        }
        $content = data_get($response->json(), 'output_text')
            ?? data_get($response->json(), 'output.0.content.0.text');
        if (! is_string($content)) {
            throw new \RuntimeException('AI provider returned no draft.');
        }

        return $content;
    }

    private function gemini(array $provider, string $prompt, ?string $image): string
    {
        $base = rtrim($provider['endpoint'], '/');
        $url = str_contains($base, ':generateContent')
            ? $base
            : $base . '/models/' . rawurlencode($provider['model']) . ':generateContent';
        $parts = [['text' => $prompt]];
        if ($image !== null) {
            $parts[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $image]];
        }
        $response = Http::acceptJson()->timeout(18)->connectTimeout(5)
            ->post($url . (str_contains($url, '?') ? '&' : '?') . 'key=' . rawurlencode($provider['api_key']), [
                'contents' => [['parts' => $parts]],
                'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('AI provider request failed.');
        }
        $content = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($content)) {
            throw new \RuntimeException('AI provider returned no draft.');
        }

        return $content;
    }

    private function anthropic(array $provider, string $prompt, ?string $image): string
    {
        $url = rtrim($provider['endpoint'], '/');
        if (! str_ends_with($url, '/messages')) {
            $url .= '/messages';
        }
        $content = [];
        if ($image !== null) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $image]];
        }
        $content[] = ['type' => 'text', 'text' => $prompt];
        $response = Http::acceptJson()->withHeaders([
            'x-api-key' => $provider['api_key'],
            'anthropic-version' => '2023-06-01',
        ])->timeout(18)->connectTimeout(5)->post($url, [
            'model' => $provider['model'],
            'max_tokens' => 600,
            'temperature' => 0.1,
            'messages' => [['role' => 'user', 'content' => $content]],
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('AI provider request failed.');
        }
        $content = data_get($response->json(), 'content.0.text');
        if (! is_string($content)) {
            throw new \RuntimeException('AI provider returned no draft.');
        }

        return $content;
    }

    /**
     * Return only account-visible model identifiers for the admin UI. This
     * request contains no citizen text, image, coordinates, or API key in the
     * response. An administrator still must choose a vision-capable model and
     * verify it with the saved-connection test.
     */
    public function models(array $provider): array
    {
        try {
            $response = match ($provider['type']) {
                'gemini' => $this->geminiModels($provider),
                'anthropic' => $this->anthropicModels($provider),
                default => $this->openAiCompatibleModels($provider),
            };
            if (! $response->successful()) {
                throw new \RuntimeException('AI provider model list request failed.');
            }

            $models = match ($provider['type']) {
                'gemini' => collect((array) data_get($response->json(), 'models', []))
                    ->map(fn ($row) => preg_replace('#^models/#', '', (string) ($row['name'] ?? ''))),
                default => collect((array) data_get($response->json(), 'data', []))
                    ->map(fn ($row) => (string) ($row['id'] ?? '')),
            };

            return $models->filter(fn ($id) => is_string($id) && preg_match('/^[A-Za-z0-9._:\/-]{1,160}$/', $id))
                ->unique()->sort()->take(250)->values()->all();
        } catch (\Throwable $e) {
            logger()->warning('AI provider model sync failed', ['provider_id' => $provider['id'] ?? null]);
            throw new \RuntimeException(__('core.ai_models_failed'));
        }
    }

    private function openAiCompatibleModels(array $provider)
    {
        $url = preg_replace('#/(?:chat/completions|responses)$#', '/models', rtrim($provider['endpoint'], '/'));
        if (! is_string($url) || ! str_ends_with($url, '/models')) {
            throw new \RuntimeException('AI provider has no standard model-list endpoint.');
        }

        return Http::acceptJson()->withToken($provider['api_key'])->timeout(12)->connectTimeout(5)->get($url);
    }

    private function geminiModels(array $provider)
    {
        $base = rtrim($provider['endpoint'], '/');
        $base = preg_replace('#/models(?:/.*)?$#', '', $base) ?: $base;
        return Http::acceptJson()->timeout(12)->connectTimeout(5)
            ->get($base . '/models?key=' . rawurlencode($provider['api_key']));
    }

    private function anthropicModels(array $provider)
    {
        $url = preg_replace('#/v1/messages$#', '/v1/models', rtrim($provider['endpoint'], '/'));
        if (! is_string($url) || ! str_ends_with($url, '/models')) {
            throw new \RuntimeException('AI provider has no standard model-list endpoint.');
        }

        return Http::acceptJson()->withHeaders([
            'x-api-key' => $provider['api_key'],
            'anthropic-version' => '2023-06-01',
        ])->timeout(12)->connectTimeout(5)->get($url);
    }

    private function limitWords(string $value, int $limit): string
    {
        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_slice($words, 0, $limit));
    }

    private function validateDraft(string $content, array $categories): array
    {
        $content = trim($content);
        $decoded = json_decode($content, true);
        if (! is_array($decoded) && preg_match('/\{.*\}/s', $content, $match)) {
            $decoded = json_decode($match[0], true);
        }
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI provider returned invalid JSON.');
        }

        $categoryId = filter_var($decoded['category_id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim((string) ($decoded['title'] ?? ''));
        $description = $this->limitWords(trim((string) ($decoded['description'] ?? '')), 150);
        $confidence = filter_var($decoded['confidence'] ?? null, FILTER_VALIDATE_INT);
        $uncertainty = trim((string) ($decoded['uncertainty'] ?? ''));
        if (! $categoryId || ! isset($categories[$categoryId]) || $title === '' || $description === ''
            || $confidence === false || $confidence < 0 || $confidence > 100) {
            throw new \RuntimeException('AI provider returned an invalid draft.');
        }

        return [
            'category_id' => (int) $categoryId,
            'title' => mb_substr($title, 0, 150),
            'description' => mb_substr($description, 0, 1000),
            'confidence' => (int) $confidence,
            'uncertainty' => mb_substr($uncertainty, 0, 240),
        ];
    }
}
