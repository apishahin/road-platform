<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SystemState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Admin → System: server/app information, health checks, cache clearing,
 * soft maintenance mode, database migrations and full-site backups
 * (download / restore) — everything needed to run the site without SSH.
 */
class SystemController extends Controller
{
    private const BACKUP_DIR = 'backups'; // inside storage/app (never public)

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('admin.system', [
            'info'    => $this->systemInfo($request),
            'checks'  => $this->healthChecks($request),
            'backups' => $this->backupList(),
            'maint'   => SystemState::maintenance(),
        ]);
    }

    // ---------------------------------------------------------------- cache

    public function cache(Request $request)
    {
        $this->guard($request);
        $data = $request->validate(['type' => 'required|in:views,routes,config,app,all,log']);

        try {
            match ($data['type']) {
                'views'  => Artisan::call('view:clear'),
                'routes' => Artisan::call('route:clear'),
                'config' => Artisan::call('config:clear'),
                'app'    => Artisan::call('cache:clear'),
                'all'    => Artisan::call('optimize:clear'),
                'log'    => $this->clearLog(),
            };
        } catch (\Throwable $e) {
            return back()->withErrors(['cache' => $e->getMessage()]);
        }

        return back()->with('status', __('issues.sys_cache_cleared', ['what' => __('issues.sys_cache_'.$data['type'])]));
    }

    private function clearLog(): void
    {
        @file_put_contents(storage_path('logs/laravel.log'), '');
    }

    // --------------------------------------------------------- maintenance

    public function maintenance(Request $request)
    {
        $this->guard($request);
        $data = $request->validate([
            'on'      => 'nullable|boolean',
            'message' => 'nullable|string|max:300',
        ]);

        SystemState::save(! empty($data['on']), $data['message'] ?? null);

        return back()->with('status', __('issues.sys_maintenance_saved'));
    }

    // ------------------------------------------------------------ migrate

    public function migrate(Request $request)
    {
        $this->guard($request);

        try {
            Artisan::call('migrate', ['--force' => true]);
            $out = trim(Artisan::output());
        } catch (\Throwable $e) {
            return back()->withErrors(['migrate' => $e->getMessage()]);
        }

        return back()->with('status', __('issues.sys_migrate_done'))
            ->with('migrate_out', $out !== '' ? $out : null);
    }

    // ------------------------------------------------------------ backups

    public function backupCreate(Request $request)
    {
        $this->guard($request);
        @set_time_limit(300);

        $withUploads = $request->boolean('uploads');
        $dir = storage_path('app/'.self::BACKUP_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.zip';
        $path = $dir.'/'.$name;

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['backup' => __('issues.sys_backup_failed')]);
        }

        try {
            $zip->addFromString('database.sql', $this->dumpSql());

            // Raw sqlite file too — handy for manual recovery.
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
            ], JSON_PRETTY_PRINT));

            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($path);

            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('status', __('issues.sys_backup_created', ['name' => $name]));
    }

    public function backupDownload(Request $request, string $file)
    {
        $this->guard($request);

        return response()->download($this->backupPath($file), $file);
    }

    public function backupDelete(Request $request, string $file)
    {
        $this->guard($request);
        @unlink($this->backupPath($file));

        return back()->with('status', __('issues.sys_backup_deleted'));
    }

    public function backupRestore(Request $request)
    {
        $this->guard($request);
        @set_time_limit(600);

        $data = $request->validate([
            'backup'  => ['required', 'file', 'max:204800'], // 200 MB
            'confirm' => ['accepted'],
        ]);

        $file = $data['backup'];
        if (strtolower((string) $file->getClientOriginalExtension()) !== 'zip') {
            throw ValidationException::withMessages(['backup' => __('issues.sys_backup_bad')]);
        }

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['backup' => __('issues.sys_backup_bad')]);
        }
        $sql = $zip->getFromName('database.sql');
        $zip->close();

        if ($sql === false || ! str_contains($sql, '-- Road Platform backup')) {
            throw ValidationException::withMessages(['backup' => __('issues.sys_backup_bad')]);
        }

        $driver = DB::connection()->getDriverName();
        try {
            // FK checks must not block drops/re-creates. defer_foreign_keys
            // also works inside the test-suite's wrapping transaction.
            if ($driver === 'sqlite') {
                DB::unprepared('PRAGMA foreign_keys=OFF');
                DB::unprepared('PRAGMA defer_foreign_keys=ON');
            } else {
                DB::unprepared('SET FOREIGN_KEY_CHECKS=0');
            }
            foreach ($this->splitSql($sql) as $stmt) {
                DB::unprepared($stmt);
            }
            if ($driver === 'sqlite') {
                DB::unprepared('PRAGMA foreign_keys=ON');
            } else {
                DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => __('issues.sys_backup_restore_failed').': '.$e->getMessage()]);
        }

        return back()->with('status', __('issues.sys_backup_restored'));
    }

    // ----------------------------------------------------------- helpers

    private function backupPath(string $file): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+\.zip$/', $file) === 1 && $file === basename($file), 404);
        $path = storage_path('app/'.self::BACKUP_DIR.'/'.$file);
        abort_unless(is_file($path), 404);

        return $path;
    }

    /** @return array<int, array{name: string, size: string, date: string}> */
    private function backupList(): array
    {
        $dir  = storage_path('app/'.self::BACKUP_DIR);
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
    private function dumpSql(): string
    {
        $conn   = DB::connection();
        $driver = $conn->getDriverName();
        $pdo    = $conn->getPdo();

        $tables = $driver === 'sqlite'
            ? array_column($conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name')
            : array_map(fn ($r) => array_values((array) $r)[0], $conn->select('SHOW TABLES'));

        $quote  = function ($v) use ($pdo) {
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
     * it sits outside single-quoted / back-quoted text (handles '' and \'
     * escapes), so Bengali text with newlines or semicolons stays intact.
     *
     * @return array<int, string>
     */
    private function splitSql(string $sql): array
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

    // ------------------------------------------------------------ info

    private function systemInfo(Request $request): array
    {
        return [
            __('issues.sys_info_app') => [
                __('issues.sys_name')     => config('app.name'),
                __('issues.sys_version')  => config('app.version', '1.0.0'),
                'Laravel'                 => app()->version(),
                __('issues.sys_timezone') => config('app.timezone'),
                __('issues.sys_locale')   => app()->getLocale(),
            ],
            __('issues.sys_info_server') => [
                __('issues.sys_software') => $_SERVER['SERVER_SOFTWARE'] ?? 'artisan serve',
                'PHP'                     => PHP_VERSION,
                __('issues.sys_protocol') => $_SERVER['SERVER_PROTOCOL'] ?? '—',
                __('issues.sys_host')     => $request->getHost(),
                __('issues.sys_port')     => (string) $request->getPort(),
                __('issues.sys_ip')       => $_SERVER['SERVER_ADDR'] ?? '—',
            ],
            __('issues.sys_info_db') => [
                __('issues.sys_db_driver')     => DB::connection()->getDriverName(),
                __('issues.sys_db_size')       => $this->dbSize(),
                __('issues.sys_backups_count') => (string) count($this->backupList()),
            ],
            __('issues.sys_info_php') => [
                'memory_limit'        => (string) ini_get('memory_limit'),
                'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'post_max_size'       => (string) ini_get('post_max_size'),
                'max_execution_time'  => (string) ini_get('max_execution_time'),
            ],
            __('issues.sys_info_storage') => [
                __('issues.sys_disk_free')    => $this->human((float) (@disk_free_space(base_path()) ?: 0)),
                __('issues.sys_log_size')     => $this->human($this->logSize()),
                __('issues.sys_config_cache') => app()->configurationIsCached() ? '✓' : '—',
                __('issues.sys_route_cache')  => app()->routesAreCached() ? '✓' : '—',
            ],
        ];
    }

    /** @return array<int, array{ok: bool, label: string, hint: string}> */
    private function healthChecks(Request $request): array
    {
        $checks   = [];
        $env      = (string) config('app.env');
        $checks[] = ['ok' => $env === 'production', 'label' => __('issues.hc_env'), 'hint' => $env];

        $dbg      = (bool) config('app.debug');
        $checks[] = ['ok' => ! $dbg, 'label' => __('issues.hc_debug'), 'hint' => $dbg ? 'true' : 'false'];

        $https    = $request->isSecure() || str_starts_with((string) config('app.url'), 'https://');
        $checks[] = ['ok' => $https, 'label' => __('issues.hc_https'), 'hint' => $https ? 'HTTPS' : 'HTTP'];

        $url      = (string) config('app.url');
        $checks[] = ['ok' => $url !== '' && ! str_contains($url, 'localhost'), 'label' => __('issues.hc_url'), 'hint' => $url !== '' ? $url : '—'];

        try {
            DB::select('SELECT 1');
            $checks[] = ['ok' => true, 'label' => __('issues.hc_db'), 'hint' => DB::connection()->getDriverName()];
        } catch (\Throwable) {
            $checks[] = ['ok' => false, 'label' => __('issues.hc_db'), 'hint' => __('issues.sys_na')];
        }

        $bad = [];
        foreach (['storage/framework', 'storage/logs', 'bootstrap/cache'] as $d) {
            if (! is_writable(base_path($d))) {
                $bad[] = $d;
            }
        }
        $checks[] = ['ok' => $bad === [], 'label' => __('issues.hc_writable'), 'hint' => $bad === [] ? '✓' : implode(', ', $bad)];

        $log      = $this->logSize();
        $checks[] = ['ok' => $log < 50 * 1024 * 1024, 'label' => __('issues.hc_log'), 'hint' => $this->human($log)];

        $n        = count($this->backupList());
        $checks[] = ['ok' => $n > 0, 'label' => __('issues.hc_backup'), 'hint' => (string) $n];

        $m        = SystemState::maintenance();
        $checks[] = ['ok' => ! $m['on'], 'label' => __('issues.hc_maint'), 'hint' => $m['on'] ? 'ON' : 'OFF'];

        return $checks;
    }

    private function dbSize(): string
    {
        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $p = (string) config('database.connections.sqlite.database');

                return $p !== '' && $p !== ':memory:' && is_file($p) ? $this->human((int) filesize($p)) : __('issues.sys_na');
            }
            $r = DB::selectOne('SELECT SUM(data_length + index_length) AS s FROM information_schema.tables WHERE table_schema = DATABASE()');

            return $this->human((int) ($r->s ?? 0));
        } catch (\Throwable) {
            return __('issues.sys_na');
        }
    }

    private function logSize(): int
    {
        $n = 0;
        foreach (glob(storage_path('logs/*.log')) ?: [] as $f) {
            $n += (int) @filesize($f);
        }

        return $n;
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
