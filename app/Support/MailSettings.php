<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MailSettings
{
    public const KEY = 'mail_settings';
    public const MODES = ['log', 'smtp', 'gmail'];

    public static function defaults(): array
    {
        return [
            'mode' => 'log',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => '',
            'from_name' => '',
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

    public static function all(): array
    {
        return array_merge(self::defaults(), self::raw() ?? []);
    }

    public static function hasStored(): bool
    {
        return self::raw() !== null;
    }

    public static function hasPassword(): bool
    {
        return (string) self::all()['password'] !== '';
    }

    public static function save(array $new, string $plainPassword = '', bool $clearPassword = false): void
    {
        $data = array_merge(self::all(), $new);
        if ($clearPassword) {
            $data['password'] = '';
        }
        if ($plainPassword !== '') {
            $data['password'] = Crypt::encryptString($plainPassword);
        }
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($data), 'updated_at' => now()]
        );
    }

    public static function apply(): void
    {
        $raw = self::raw();
        if (! $raw) {
            return;
        }
        $s = array_merge(self::defaults(), $raw);
        if (! in_array($s['mode'], self::MODES, true)) {
            return;
        }

        if ($s['mode'] === 'log') {
            config(['mail.default' => 'log']);
        } else {
            $pass = '';
            if ($s['password'] !== '') {
                try {
                    $pass = Crypt::decryptString($s['password']);
                } catch (\Throwable $e) {
                    $pass = '';
                }
            }

            if ($s['mode'] === 'gmail') {
                $host = 'smtp.gmail.com';
                $port = 587;
                $scheme = 'smtp';
                $s['from_address'] = $s['username'];
            } else {
                $host = $s['host'];
                $port = (int) $s['port'];
                $scheme = $s['encryption'] === 'ssl' ? 'smtps' : 'smtp';
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.scheme' => $scheme,
                'mail.mailers.smtp.username' => $s['username'] !== '' ? $s['username'] : null,
                'mail.mailers.smtp.password' => $pass !== '' ? $pass : null,
            ]);
        }

        if ($s['from_address'] !== '') {
            config(['mail.from.address' => $s['from_address']]);
        }
        if ($s['from_name'] !== '') {
            config(['mail.from.name' => $s['from_name']]);
        }
    }
}