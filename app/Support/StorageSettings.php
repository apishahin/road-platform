<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Which disks hold evidence photos. The choice (local / r2) is stored in the
 * settings table. Credentials are never stored here; they stay in .env.
 */
class StorageSettings
{
    public const OPTIONS = [
        'local' => ['private' => 'private',    'public' => 'public'],
        'r2'    => ['private' => 'r2_private', 'public' => 'r2_public'],
    ];

    private static ?string $stored = null;
    private static bool $loaded = false;

    private static function stored(): ?string
    {
        if (self::$loaded) {
            return self::$stored;
        }
        self::$loaded = true;

        try {
            if (Schema::hasTable('settings')) {
                $value = DB::table('settings')->where('key', 'evidence_storage')->value('value');
                if ($value !== null && isset(self::OPTIONS[$value])) {
                    self::$stored = $value;
                }
            }
        } catch (\Throwable) {
            // database not ready: fall back to .env
        }

        return self::$stored;
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
        self::$stored = $choice;
        self::$loaded = true;
    }

    /** True only if every R2 setting exists in .env. Never exposes the values. */
    public static function r2Configured(): bool
    {
        foreach (['r2_private', 'r2_public'] as $disk) {
            $c = config("filesystems.disks.$disk");
            if (! $c || empty($c['bucket']) || empty($c['key']) || empty($c['secret']) || empty($c['endpoint'])) {
                return false;
            }
        }

        return ! empty(config('filesystems.disks.r2_public.url'));
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