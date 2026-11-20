<?php

namespace App\Services;

use App\Models\PushToken;
use App\Support\PushSettings;
use Illuminate\Support\Facades\Http;

/**
 * Sends Firebase Cloud Messaging (HTTP v1) pushes to users' devices.
 * 100% fail-soft: any error is logged and swallowed — never breaks requests.
 */
class PushService
{
    public static function sendToUsers(array $userIds, string $title, string $body, string $url): void
    {
        try {
            if ($userIds === [] || ! PushSettings::on()) {
                return;
            }
            $sa = PushSettings::serviceAccount();
            if (! $sa) {
                return;
            }
            $tokens = PushToken::whereIn('user_id', $userIds)->pluck('token')->unique()->take(200);
            if ($tokens->isEmpty()) {
                return;
            }
            $access = self::accessToken($sa);
            if (! $access) {
                return;
            }

            $endpoint = 'https://fcm.googleapis.com/v1/projects/' . $sa['project_id'] . '/messages:send';
            foreach ($tokens as $token) {
                $r = Http::withToken($access)->timeout(10)->post($endpoint, [
                    'message' => [
                        'token'        => $token,
                        'notification' => ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 200)],
                        'data'         => ['url' => $url],
                        'webpush'      => ['fcm_options' => ['link' => $url]],
                    ],
                ]);
                if ($r->status() === 404) {
                    PushToken::where('token', $token)->delete(); // stale device
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** OAuth2 access token from the service account (JWT bearer, RS256 via openssl). */
    private static function accessToken(array $sa): ?string
    {
        try {
            $now = time();
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
                . '.' . $b64(json_encode([
                    'iss'   => $sa['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud'   => 'https://oauth2.googleapis.com/token',
                    'iat'   => $now,
                    'exp'   => $now + 3600,
                ]));
            $sig = '';
            if (! openssl_sign($jwt, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }
            $jwt .= '.' . $b64($sig);

            $r = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            return $r->successful() ? ($r->json('access_token') ?: null) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
