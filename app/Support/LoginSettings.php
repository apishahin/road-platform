<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Admin-controlled login options, stored as one JSON value in `settings`.
 * Defaults keep today's behaviour (phone + OTP only).
 */
class LoginSettings
{
    public const KEY = 'login_settings';
    public const PROVIDERS = ['log', 'gateway', 'firebase', 'telegram'];
    public const DESIGNS = ['soft', 'split', 'minimal'];
    public const OTP_RESEND_MIN_SECONDS = 15;
    public const OTP_RESEND_MAX_SECONDS = 600;
    public const BOOLS = ['otp_enabled', 'password_enabled', 'google_enabled', 'registration_open', 'password_for_staff', 'require_verified_phone'];

    public static function defaults(): array
    {
        return [
            'otp_enabled'        => true,
            'password_enabled'   => false,
            'google_enabled'     => false,
            'registration_open'  => true,
            'password_for_staff' => false,
            'require_verified_phone' => false,
            'otp_provider'       => 'log',
            // Public login only: wait before the same number can request a
            // replacement OTP. The server enforces this, not only the UI.
            'otp_resend_seconds' => 60,
            // The public login screen can be changed by an admin without code.
            'login_design'       => 'soft',
            // Google is a direct, one-tap path by default. Admins may still
            // require a mobile number after Google sign-in in Login settings.
            'google_phone'       => 'optional',
            'firebase_api_key' => '',
            'firebase_auth_domain' => '',
            'firebase_project_id' => '',
        ];
    }

    private static function raw(): ?array
    {
        try {
            $v = DB::table('settings')->where('key', self::KEY)->value('value');
        } catch (\Throwable $e) {
            return null;
        }
        if (! $v) {
            return null;
        }
        $a = json_decode($v, true);

        return is_array($a) ? $a : null;
    }

    public static function sanitize(array $in): array
    {
        $out = [];
        foreach (self::BOOLS as $k) {
            if (array_key_exists($k, $in)) {
                $out[$k] = filter_var($in[$k], FILTER_VALIDATE_BOOLEAN);
            }
        }
        foreach (['firebase_api_key', 'firebase_auth_domain', 'firebase_project_id'] as $fk) {
            if (array_key_exists($fk, $in)) {
                $out[$fk] = trim((string) $in[$fk]);
            }
        }
        if (isset($in['otp_provider']) && in_array($in['otp_provider'], self::PROVIDERS, true)) {
            $out['otp_provider'] = $in['otp_provider'];
        }
        if (array_key_exists('otp_resend_seconds', $in)
            && filter_var($in['otp_resend_seconds'], FILTER_VALIDATE_INT) !== false) {
            $out['otp_resend_seconds'] = min(self::OTP_RESEND_MAX_SECONDS, max(
                self::OTP_RESEND_MIN_SECONDS,
                (int) $in['otp_resend_seconds']
            ));
        }
        if (isset($in['google_phone']) && in_array($in['google_phone'], ['required', 'optional'], true)) {
            $out['google_phone'] = $in['google_phone'];
        }
        if (isset($in['login_design']) && in_array($in['login_design'], self::DESIGNS, true)) {
            $out['login_design'] = $in['login_design'];
        }

        return $out;
    }

    public static function all(): array
    {
        return array_merge(self::defaults(), self::sanitize(self::raw() ?? []));
    }

    public static function anyMethodEnabled(?array $s = null): bool
    {
        $s = $s ?? self::all();

        return ! empty($s['otp_enabled']) || ! empty($s['password_enabled'])
            || ! empty($s['google_enabled']) || ! empty($s['password_for_staff']);
    }

    public static function save(array $new): void
    {
        $data = array_merge(self::all(), self::sanitize($new));
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($data), 'updated_at' => now()]
        );
    }

    /** Firebase web config for the browser (empty strings when unset). */
    public static function firebaseConfig(): array
    {
        $cfg = self::all();

        return [
            'apiKey'     => (string) $cfg['firebase_api_key'],
            'authDomain' => (string) $cfg['firebase_auth_domain'],
            'projectId'  => (string) $cfg['firebase_project_id'],
        ];
    }

    public static function otpEnabled(): bool { return (bool) self::all()['otp_enabled']; }
    public static function passwordEnabled(): bool { return (bool) self::all()['password_enabled']; }
    public static function googleEnabled(): bool { return (bool) self::all()['google_enabled']; }
    public static function registrationOpen(): bool { return (bool) self::all()['registration_open']; }
    public static function passwordForStaff(): bool { return (bool) self::all()['password_for_staff']; }

    /** When on, citizens must verify their number before contributing (report/observe/verify/volunteer). */
    public static function requireVerifiedPhone(): bool { return (bool) self::all()['require_verified_phone']; }
    public static function provider(): string { return (string) self::all()['otp_provider']; }
    public static function otpResendSeconds(): int { return (int) self::all()['otp_resend_seconds']; }

    /** Whether Google sign-in must be followed by a mobile number ('required'|'optional'). */
    public static function googlePhoneMode(): string { return (string) self::all()['google_phone']; }

    /** Admin-selected public login screen design. */
    public static function design(): string { return (string) self::all()['login_design']; }
}