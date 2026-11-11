<?php

namespace App\Services;

use App\Models\SmsGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generic sender: tries enabled gateways (by priority) until one succeeds.
 * Never writes the API key anywhere.
 */
class GatewaySender
{
    /** @return array{ok: bool, tried: array<int, array{name: string, ok: bool, note: string}>} */
    public function send(string $phone, string $message, string $channel = 'sms'): array
    {
        $tried = [];
        $list = SmsGateway::where('enabled', true)
            ->where('channel', $channel)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($list as $g) {
            [$ok, $note] = $this->attempt($g, $phone, $message);
            $tried[] = ['name' => $g->name, 'ok' => $ok, 'note' => $note];
            if ($ok) {
                return ['ok' => true, 'tried' => $tried];
            }
        }

        return ['ok' => false, 'tried' => $tried];
    }

    /** @return array{0: bool, 1: string} */
    public function attempt(SmsGateway $g, string $phone, string $message): array
    {
        $key = $g->apiKey();
        $plain = ltrim($phone, '+');
        $vars = [
            '{to}' => $plain,
            '{to_plus}' => '+' . $plain,
            '{message}' => $message,
            '{api_key}' => $key,
            '{sender_id}' => (string) $g->sender_id,
        ];

        try {
            if (!str_starts_with(strtolower((string) $g->url), 'https://')) {
                return $this->done($g, false, 'URL must start with https://', $key);
            }

            $enc = array_map('rawurlencode', $vars);
            $url = strtr((string) $g->url, $enc);
            $params = $this->fill($this->decode($g->params), $vars);
            $headers = $this->fill($this->decode($g->headers), $vars);

            $req = Http::timeout(10)->withHeaders($headers);

            if (strtoupper((string) $g->method) === 'GET') {
                $base = $url;
                $q = [];
                if (str_contains($url, '?')) {
                    [$base, $qs] = explode('?', $url, 2);
                    parse_str($qs, $q);
                }
                $query = array_merge($q, $params);
                $res = $query ? $req->get($base, $query) : $req->get($base);
            } elseif ($g->body_type === 'json') {
                $res = $req->asJson()->post($url, $params);
            } else {
                $res = $req->asForm()->post($url, $params);
            }

            $body = (string) $res->body();
            $marker = (string) $g->success_marker;
            $ok = $res->successful() && ($marker === '' || str_contains($body, $marker));

            return $this->done($g, $ok, 'HTTP ' . $res->status() . ' ' . $body, $key);
        } catch (Throwable $e) {
            return $this->done($g, false, 'error: ' . get_class($e) . ' ' . $e->getMessage(), $key);
        }
    }

    private function done(SmsGateway $g, bool $ok, string $note, string $key): array
    {
        $note = $this->clean($note, $key);
        $g->forceFill([
            'last_status' => $ok ? 'ok' : 'fail',
            'last_note' => $note,
            'last_used_at' => now(),
        ])->save();

        return [$ok, $note];
    }

    private function clean(string $text, string $key): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        if ($key !== '') {
            $text = str_replace([$key, rawurlencode($key), urlencode($key)], '***', $text);
        }

        return mb_substr(trim($text), 0, 200);
    }

    /** @return array<string, mixed> */
    private function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $a = json_decode($json, true);

        return is_array($a) ? $a : [];
    }

    /** @return array<string, mixed> */
    private function fill(array $a, array $vars): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) { $a[$k] = $this->fill($v, $vars); } elseif (is_string($v)) {
                $a[$k] = strtr($v, $vars);
            }
        }

        return $a;
    }
}