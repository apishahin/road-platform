<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Full-site backups: a zip with a pure-PHP SQL dump (works on sqlite and
 * mysql without shell tools), the raw sqlite file when present, .env and
 * optionally public/uploads. Also used by the scheduled auto-backup.
 */
class BackupService
{
    public const DIR = 'backups'; // inside storage/app (never public)

    /** How many scheduled auto-backups to keep. */
    public const AUTO_KEEP = 10;

    /** Create a backup zip; returns the file name. Throws on failure. */
    public function create(bool $withUploads, string $prefix = 'backup'): string
    {
        @set_time_limit(300);

        $dir = storage_path('app/'.self::DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = $prefix.'-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.zip';
        $path = $dir.'/'.$name;

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create backup zip.');
        }

        try {
            $zip->addFromString('database.sql', $this->dumpSql());

            $dbPath = (string) config('database.connections.sqlite.database');
            if (config('database.default') === 'sqlite' && $dbPath !== '' && $dbPath !== ':memory:' && is_file($dbPath)) {
                $zip->addFile($dbPath, 'database.sqlite');
            }

            $env = base_path('.env');
            if (is_file($env)) {
                $zip->addFile($env, 'env.txt');
            }

            if ($withUploads && is_dir(public_path('uploads'))) {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(public_path('uploads'), \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $f) {
                    if ($f->isFile()) {
                        $zip->addFile($f->getPathname(), 'uploads/'.$it->getSubPathName());
                    }
                }
            }

            $zip->addFromString('backup.json', json_encode([
                'created_at'  => now()->toIso8601String(),
                'app_version' => config('app.version'),
                'laravel'     => app()->version(),
                'db_driver'   => DB::connection()->getDriverName(),
                'uploads'     => $withUploads,
                'automatic'   => $prefix === 'auto',
            ], JSON_PRETTY_PRINT));

            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($path);

            throw $e;
        }

        return $name;
    }

    /** Keep only the newest AUTO_KEEP scheduled backups. */
    public function pruneAuto(): int
    {
        $auto = array_values(array_filter($this->list(), fn ($b) => str_starts_with($b['name'], 'auto-')));
        $deleted = 0;
        foreach (array_slice($auto, self::AUTO_KEEP) as $b) {
            @unlink(storage_path('app/'.self::DIR.'/'.$b['name']));
            $deleted++;
        }

        return $deleted;
    }

    /** Validated absolute path for a stored backup (404-safe). */
    public function path(string $file): ?string
    {
        if (preg_match('/^[A-Za-z0-9._-]+\.zip$/', $file) !== 1 || $file !== basename($file)) {
            return null;
        }
        $path = storage_path('app/'.self::DIR.'/'.$file);

        return is_file($path) ? $path : null;
    }

    /** @return array<int, array{name: string, size: string, date: string}> newest first */
    public function list(): array
    {
        $dir  = storage_path('app/'.self::DIR);
        $rows = [];
        foreach (is_dir($dir) ? glob($dir.'/*.zip') ?: [] : [] as $p) {
            $rows[] = [
                'name'  => basename($p),
                'size'  => $this->human((int) @filesize($p)),
                'date'  => date('Y-m-d H:i', (int) @filemtime($p)),
                'mtime' => (int) @filemtime($p),
            ];
        }
        usort($rows, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $rows;
    }

    /** Pure-PHP SQL dump — works for sqlite and mysql without shell tools. */
    public function dumpSql(): string
    {
        $conn   = DB::connection();
        $driver = $conn->getDriverName();
        $pdo    = $conn->getPdo();

        $tables = $driver === 'sqlite'
            ? array_column($conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name')
            : array_map(fn ($r) => array_values((array) $r)[0], $conn->select('SHOW TABLES'));

        $quote = function ($v) use ($pdo) {
            if ($v === null) {
                return 'NULL';
            }
            if (is_bool($v)) {
                return $v ? '1' : '0';
            }

            return $pdo->quote((string) $v);
        };

        $out = "-- Road Platform backup\n-- ".now()->toDateTimeString()."\n";
        foreach ($tables as $t) {
            $qt = $driver === 'sqlite' ? '"'.$t.'"' : '`'.$t.'`';
            $create = $driver === 'sqlite'
                ? ($conn->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$t])->sql ?? null)
                : ($conn->selectOne("SHOW CREATE TABLE `$t`")->{'Create Table'} ?? null);
            if (! $create) {
                continue;
            }

            $out .= "\nDROP TABLE IF EXISTS $qt;\n$create;\n";
            $conn->table($t)->orderByRaw('1')->chunk(500, function ($rows) use (&$out, $qt, $quote, $driver) {
                $rows = $rows->map(fn ($r) => (array) $r);
                if ($rows->isEmpty()) {
                    return;
                }
                $qc = implode(',', array_map(fn ($c) => $driver === 'sqlite' ? '"'.$c.'"' : '`'.$c.'`', array_keys($rows->first())));
                $vals = [];
                foreach ($rows as $r) {
                    $vals[] = '('.implode(',', array_map($quote, array_values($r))).')';
                }
                $out .= "INSERT INTO $qt ($qc) VALUES\n".implode(",\n", $vals).";\n";
            });
        }

        return $out;
    }

    /**
     * Split a dump into statements: a semicolon ends a statement only when
     * outside single-quoted / back-quoted text (handles '' and \' escapes),
     * so text with newlines or semicolons stays intact.
     *
     * @return array<int, string>
     */
    public function splitSql(string $sql): array
    {
        $stmts = [];
        $buf   = '';
        $q     = null;
        $len   = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($q !== null) {
                $buf .= $ch;
                if ($ch === '\\' && $q === "'" && $i + 1 < $len) {
                    $buf .= $sql[++$i];

                    continue;
                }
                if ($ch === $q) {
                    if ($q === "'" && $i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= "'";
                        $i++;

                        continue;
                    }
                    $q = null;
                }

                continue;
            }
            if ($ch === "'" || $ch === '`') {
                $q = $ch;
                $buf .= $ch;

                continue;
            }
            if ($ch === ';') {
                $stmts[] = $buf;
                $buf     = '';

                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $stmts[] = $buf;
        }

        return array_values(array_filter(array_map('trim', $stmts), fn ($s) => $s !== ''));
    }

    private function human(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $bytes = (float) $bytes;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $bytes : number_format($bytes, 1)).' '.$units[$i];
    }
}
