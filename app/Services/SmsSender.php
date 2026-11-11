<?php

namespace App\Services;

use App\Support\LoginSettings;
use Illuminate\Support\Facades\Log;

/**
 * The only place that sends SMS.
 * Provider "gateway": tries enabled WhatsApp gateways first, then SMS gateways.
 * Otherwise: local env writes the message to storage/logs/laravel.log.
 */
class SmsSender
{
    public function send(string $phone, string $message): bool
    {
        if (LoginSettings::provider() === 'gateway') {
            $g = new GatewaySender();
            $names = [];
            foreach (['whatsapp', 'sms'] as $channel) {
                $r = $g->send($phone, $message, $channel);
                if ($r['ok']) {
                    return true;
                }
                foreach ($r['tried'] as $x) {
                    $names[] = $channel . ':' . $x['name'];
                }
            }
            Log::warning('SMS gateways failed or none enabled. Message was NOT sent. Tried: ' . implode(', ', $names));
            if (app()->environment('local')) {
                Log::info("SMS to {$phone}: {$message}");
            }

            return false;
        }

        if (app()->environment('local')) {
            Log::info("SMS to {$phone}: {$message}");

            return true;
        }

        Log::warning('SMS gateway is not configured. Message was NOT sent.');

        return false;
    }
}