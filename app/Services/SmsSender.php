<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * The only place that sends SMS.
 * Local: writes the message to storage/logs/laravel.log.
 * Production: connect a Bangladesh SMS gateway here later.
 */
class SmsSender
{
    public function send(string $phone, string $message): void
    {
        if (app()->environment('local')) {
            Log::info("SMS to {$phone}: {$message}");

            return;
        }

        // TODO: call the real SMS gateway here.
        Log::warning('SMS gateway is not configured. Message was NOT sent.');
    }
}