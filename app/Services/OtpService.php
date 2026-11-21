<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class OtpService
{
    private const TTL_MINUTES  = 5;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private SmsSender $sms)
    {
    }

    /** @return 'sent'|'wait'|'blocked' */
    public function send(string $phone, string $ip, int $resendSeconds = 60): string
    {
        // Callers for account verification retain the safe one-minute default.
        // Public login passes the admin-selected value, bounded defensively here.
        $resendSeconds = min(600, max(15, $resendSeconds));

        $banned = User::where('phone', $phone)->where('is_banned', true)->exists();
        if ($banned) {
            return 'blocked';
        }

        $cool  = 'otp-cool:' . sha1($phone);
        $byTel = 'otp-phone:' . sha1($phone);
        $byIp  = 'otp-ip:' . sha1($ip);

        if (RateLimiter::tooManyAttempts($cool, 1)
            || RateLimiter::tooManyAttempts($byTel, 3)
            || RateLimiter::tooManyAttempts($byIp, 10)) {
            return 'wait';
        }
        RateLimiter::hit($cool, $resendSeconds); // configured replacement-code wait
        RateLimiter::hit($byTel, 600);              // 3 codes per 10 minutes per phone
        RateLimiter::hit($byIp, 3600);     // 10 codes per hour per IP

        // Old unused codes stop working.
        DB::table('otp_codes')->where('phone', $phone)->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $row = [
            'phone'      => $phone,
            'code_hash'  => $this->hash($phone, $code),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'used_at'    => null,
        ];
        if (Schema::hasColumn('otp_codes', 'created_at')) {
            $row['created_at'] = now();
        }
        if (Schema::hasColumn('otp_codes', 'updated_at')) {
            $row['updated_at'] = now();
        }
        DB::table('otp_codes')->insert($row);

        $smsOk = $this->sms->send($phone, "Road Platform code: {$code}. Valid for " . self::TTL_MINUTES . ' minutes. Do not share it.');

        return ($smsOk === false && \App\Support\LoginSettings::provider() === 'gateway') ? 'failed' : 'sent';
    }

    /** Seconds until the next permitted login OTP request for this phone/IP pair. */
    public function waitSeconds(string $phone, string $ip): int
    {
        $keys = [
            'otp-cool:' . sha1($phone),
            'otp-phone:' . sha1($phone),
            'otp-ip:' . sha1($ip),
        ];

        return max(0, ...array_map(fn (string $key) => RateLimiter::availableIn($key), $keys));
    }

    /** @return 'ok'|'invalid'|'locked' */
    public function verify(string $phone, string $code): string
    {
        $row = DB::table('otp_codes')
            ->where('phone', $phone)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            return 'invalid';
        }
        if ($row->attempts >= self::MAX_ATTEMPTS) {
            return 'locked';
        }

        if (hash_equals($row->code_hash, $this->hash($phone, $code))) {
            // One use only: the update succeeds for the first request only.
            $used = DB::table('otp_codes')->where('id', $row->id)->whereNull('used_at')
                ->update(['used_at' => now()]);

            return $used === 1 ? 'ok' : 'invalid';
        }

        DB::table('otp_codes')->where('id', $row->id)->increment('attempts');

        return ($row->attempts + 1) >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
    }

    /** Find or create the user after a successful code check. Null if banned. */
    public function userFor(string $phone): ?User
    {
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return User::create([
                'phone'             => $phone,
                'display_name'      => 'User',
                'role'              => 'citizen',
                'phone_verified_at' => now(),
            ]);
        }
        if ($user->is_banned) {
            return null;
        }
        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return $user;
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone . '|' . $code, (string) config('app.key'));
    }
}