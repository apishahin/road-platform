<?php

namespace App\Http\Middleware;

use App\Support\ApiKeySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Gate for the public read-only API:
 *  - keyless access is allowed (lower rate limit) when "public read" is on
 *  - a valid X-Api-Key / ?api_key raises the limit and gets usage-tracked
 *  - invalid keys are always rejected, even when public read is on
 */
class ApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-Api-Key') ?: $request->query('api_key');
        $token = is_string($token) ? trim($token) : '';
        $key   = $token !== '' ? ApiKeySettings::findByToken($token) : null;

        if ($token !== '' && ! $key) {
            return $this->deny('invalid_api_key', 'The API key is not valid.', 401);
        }
        if (! $key && ! ApiKeySettings::publicRead()) {
            return $this->deny('api_key_required', 'An API key is required. Ask the site admin for one.', 401);
        }

        $bucket = $key ? 'api-key:' . $key['id'] : 'api-ip:' . $request->ip();
        $limit  = $key ? ApiKeySettings::RATE_KEYED : ApiKeySettings::RATE_PUBLIC;

        if (RateLimiter::tooManyAttempts($bucket, $limit)) {
            return $this->deny('rate_limited', 'Too many requests — slow down.', 429,
                ['Retry-After' => (string) RateLimiter::availableIn($bucket)]);
        }
        RateLimiter::hit($bucket, 60);

        if ($key) {
            ApiKeySettings::touch($key['id']);
        }

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);

        return $response;
    }

    private function deny(string $code, string $message, int $status, array $headers = [])
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status, $headers);
    }
}
