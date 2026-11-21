<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Which disks hold evidence photos and the optional Cloudflare R2 connection.
 *
 * The active destination and R2 values are stored in the settings table so an
 * administrator can operate them without editing .env. R2 credentials are
 * encrypted with APP_KEY before they reach the database. Existing .env values
 * remain a safe legacy fallback until the Admin form is saved.
 */
class StorageSettings
{
    public const OPTIONS = [
        'local' => ['private' => 'private', 'public' => 'public'],
        'r2' => ['private' => 'r2_private', 'public' => 'r2_public'],
    ];

    public const R2_KEY = 'r2_storage_settings';

    private static function stored(): ?string
    {
        try {
            if (Schema::hasTable('settings')) {
                $value = DB::table('settings')->where('key', 'evidence_storage')->value('value');
                if ($value !== null && isset(self::OPTIONS[$value])) {
                    return $value;
                }
            }
        } catch (\Throwable) {
            // Database not ready: fall back to configuration values.
        }

        return null;
    }

    public static function choice(): string
    {
        $stored = self::stored();
        if ($stored !== null) {
            return $stored;
        }

        return str_starts_with((string) config('filesystems.evidence.private'), 'r2') ? 'r2' : 'local';
    }

    /**
     * Whether the untouched original upload is also kept (private disk).
     * Default ON = today's behaviour. Turning it OFF roughly halves storage
     * for new photos: only the compressed 1600px copy is kept.
     */
    public static function keepOriginals(): bool
    {
        try {
            $v = DB::table('settings')->where('key', 'keep_originals')->value('value');

            return $v === null ? true : $v === '1';
        } catch (\Throwable) {
            return true;
        }
    }

    public static function setKeepOriginals(bool $on): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'keep_originals'],
            ['value' => $on ? '1' : '0', 'updated_at' => now()]
        );
    }

    public static function privateDisk(): string
    {
        $stored = self::stored();

        return $stored !== null
            ? self::OPTIONS[$stored]['private']
            : (string) config('filesystems.evidence.private');
    }

    public static function publicDisk(): string
    {
        $stored = self::stored();

        return $stored !== null
            ? self::OPTIONS[$stored]['public']
            : (string) config('filesystems.evidence.public');
    }

    public static function set(string $choice): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'evidence_storage'],
            ['value' => $choice, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * The non-secret R2 values from .env/config. These form the fallback for
     * existing installations which have not yet saved the Admin form.
     */
    private static function r2Defaults(): array
    {
        return [
            'endpoint' => (string) config('filesystems.disks.r2_private.endpoint', ''),
            'private_bucket' => (string) config('filesystems.disks.r2_private.bucket', ''),
            'public_bucket' => (string) config('filesystems.disks.r2_public.bucket', ''),
            'public_url' => (string) config('filesystems.disks.r2_public.url', ''),
            'access_key_id' => (string) config('filesystems.disks.r2_private.key', ''),
            'secret_access_key' => (string) config('filesystems.disks.r2_private.secret', ''),
        ];
    }

    private static function r2Raw(): ?array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }
            $raw = DB::table('settings')->where('key', self::R2_KEY)->value('value');
        } catch (\Throwable) {
            return null;
        }

        $data = json_decode((string) $raw, true);

        return is_array($data) ? $data : null;
    }

    private static function decryptR2Value(array $raw, string $key, string $fallback): string
    {
        if (! array_key_exists($key, $raw)) {
            return $fallback;
        }
        if ((string) $raw[$key] === '') {
            return '';
        }

        try {
            return Crypt::decryptString((string) $raw[$key]);
        } catch (\Throwable) {
            // APP_KEY may have changed, so never try an unreadable credential.
            return '';
        }
    }

    /**
     * Effective R2 configuration. The credentials are only returned to server
     * code; views must use r2ForForm(), which deliberately excludes them.
     */
    public static function r2(): array
    {
        $settings = self::r2Defaults();
        $raw = self::r2Raw();
        if (! $raw) {
            return $settings;
        }

        foreach (['endpoint', 'private_bucket', 'public_bucket', 'public_url'] as $field) {
            if (array_key_exists($field, $raw)) {
                $settings[$field] = trim((string) $raw[$field]);
            }
        }
        $settings['access_key_id'] = self::decryptR2Value($raw, 'access_key_id', $settings['access_key_id']);
        $settings['secret_access_key'] = self::decryptR2Value($raw, 'secret_access_key', $settings['secret_access_key']);

        return $settings;
    }

    /** Values safe to put in the Admin page — credentials are never exposed. */
    public static function r2ForForm(): array
    {
        $r2 = self::r2();

        return [
            'endpoint' => $r2['endpoint'],
            'private_bucket' => $r2['private_bucket'],
            'public_bucket' => $r2['public_bucket'],
            'public_url' => $r2['public_url'],
            'has_access_key_id' => $r2['access_key_id'] !== '',
            'has_secret_access_key' => $r2['secret_access_key'] !== '',
            'stored_in_database' => self::r2Raw() !== null,
        ];
    }

    public static function hasStoredR2(): bool
    {
        return self::r2Raw() !== null;
    }

    /**
     * Persist all R2 fields. Empty credential fields retain their effective
     * current value, unless the Admin explicitly selected their clear option.
     */
    public static function saveR2(
        array $values,
        string $accessKeyId = '',
        string $secretAccessKey = '',
        bool $clearAccessKeyId = false,
        bool $clearSecretAccessKey = false,
    ): void {
        $current = self::r2();
        $accessKeyId = trim($accessKeyId);
        $secretAccessKey = trim($secretAccessKey);

        if ($clearAccessKeyId) {
            $current['access_key_id'] = '';
        } elseif ($accessKeyId !== '') {
            $current['access_key_id'] = $accessKeyId;
        }
        if ($clearSecretAccessKey) {
            $current['secret_access_key'] = '';
        } elseif ($secretAccessKey !== '') {
            $current['secret_access_key'] = $secretAccessKey;
        }

        $data = [
            'endpoint' => rtrim(trim((string) $values['endpoint']), '/'),
            'private_bucket' => trim((string) $values['private_bucket']),
            'public_bucket' => trim((string) $values['public_bucket']),
            'public_url' => rtrim(trim((string) $values['public_url']), '/'),
            'access_key_id' => $current['access_key_id'] === '' ? '' : Crypt::encryptString($current['access_key_id']),
            'secret_access_key' => $current['secret_access_key'] === '' ? '' : Crypt::encryptString($current['secret_access_key']),
        ];

        DB::table('settings')->updateOrInsert(
            ['key' => self::R2_KEY],
            ['value' => json_encode($data), 'updated_at' => now()]
        );
    }

    /** Apply encrypted database settings to Laravel's R2 disks for this request. */
    public static function apply(): void
    {
        $r2 = self::r2();

        config([
            'filesystems.disks.r2_private.key' => $r2['access_key_id'] !== '' ? $r2['access_key_id'] : null,
            'filesystems.disks.r2_private.secret' => $r2['secret_access_key'] !== '' ? $r2['secret_access_key'] : null,
            'filesystems.disks.r2_private.bucket' => $r2['private_bucket'] !== '' ? $r2['private_bucket'] : null,
            'filesystems.disks.r2_private.endpoint' => $r2['endpoint'] !== '' ? $r2['endpoint'] : null,
            'filesystems.disks.r2_public.key' => $r2['access_key_id'] !== '' ? $r2['access_key_id'] : null,
            'filesystems.disks.r2_public.secret' => $r2['secret_access_key'] !== '' ? $r2['secret_access_key'] : null,
            'filesystems.disks.r2_public.bucket' => $r2['public_bucket'] !== '' ? $r2['public_bucket'] : null,
            'filesystems.disks.r2_public.endpoint' => $r2['endpoint'] !== '' ? $r2['endpoint'] : null,
            'filesystems.disks.r2_public.url' => $r2['public_url'] !== '' ? $r2['public_url'] : null,
        ]);
    }

    /** True only if every R2 setting has a usable value. Never exposes them. */
    public static function r2Configured(): bool
    {
        $r2 = self::r2();

        foreach (['access_key_id', 'secret_access_key', 'endpoint', 'private_bucket', 'public_bucket', 'public_url'] as $field) {
            if ($r2[$field] === '') {
                return false;
            }
        }

        return true;
    }

    /** Write, read back and delete a tiny file. */
    public static function check(string $disk): array
    {
        if (config("filesystems.disks.$disk") === null) {
            return ['ok' => false, 'message' => 'ডিস্কের কনফিগারেশন নেই'];
        }

        $path = 'healthcheck/' . Str::random(12) . '.txt';

        try {
            $fs = Storage::disk($disk);
            $fs->put($path, 'ok');
            $ok = $fs->get($path) === 'ok';
            $fs->delete($path);

            return ['ok' => $ok, 'message' => $ok ? 'লেখা, পড়া ও মুছা ঠিক আছে' : 'ফাইল ফেরত পড়া যায়নি'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => Str::limit(class_basename($e) . ': ' . $e->getMessage(), 160)];
        }
    }
}
