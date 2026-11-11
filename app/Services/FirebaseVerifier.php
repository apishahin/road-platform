<?php

namespace App\Services;

use App\Support\LoginSettings;
use Illuminate\Support\Facades\Http;

class FirebaseVerifier
{
    /** Returns the verified phone number (E.164) or null. */
    public function phoneFor(string $idToken): ?string
    {
        $key = trim((string) (LoginSettings::all()['firebase_api_key'] ?? ''));
        if ($key === '' || substr_count($idToken, '.') !== 2) {
            return null;
        }

        $claims = $this->claims($idToken);
        if (! $claims) {
            return null;
        }
        if (($claims['firebase']['sign_in_provider'] ?? '') !== 'phone') {
            return null;
        }
        if ((int) ($claims['auth_time'] ?? 0) < time() - 300) {
            return null;
        }

        try {
            $res = Http::timeout(10)->asJson()->post(
                'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($key),
                ['idToken' => $idToken]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (! $res->successful()) {
            return null;
        }

        $phone = $res->json('users.0.phoneNumber');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    /** Reads the token payload. The signature itself is checked by Google above. */
    private function claims(string $idToken): ?array
    {
        $p = explode('.', $idToken)[1];
        $p = strtr($p, '-_', '+/');
        $p .= str_repeat('=', (4 - strlen($p) % 4) % 4);
        $json = base64_decode($p, true);
        $a = $json === false ? null : json_decode($json, true);

        return is_array($a) ? $a : null;
    }
}