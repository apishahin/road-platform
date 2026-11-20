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
            'backups' => app(\App\Services\BackupService::class)->list(),
            'autoBackup' => \App\Support\SystemState::autoBackupOn(),
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

        try {
            $name = app(\App\Services\BackupService::class)->create($request->boolean('uploads'));
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('status', __('issues.sys_backup_created', ['name' => $name]));
    }

    public function backupDownload(Request $request, string $file)
    {
        $this->guard($request);
        $path = app(\App\Services\BackupService::class)->path($file);
        abort_if($path === null, 404);

        return response()->download($path, $file);
    }

    public function backupDelete(Request $request, string $file)
    {
        $this->guard($request);
        $path = app(\App\Services\BackupService::class)->path($file);
        abort_if($path === null, 404);
        @unlink($path);

        return back()->with('status', __('issues.sys_backup_deleted'));
    }

    public function autoBackup(Request $request)
    {
        $this->guard($request);

        \App\Support\SystemState::saveAutoBackup($request->boolean('on'));

        return back()->with('status', __('issues.sys_auto_backup_saved'));
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
            foreach (app(\App\Services\BackupService::class)->splitSql($sql) as $stmt) {
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

    /**
     * Split a dump into statements: a semicolon ends a statement only when
     * it sits outside single-quoted / back-quoted text (handles '' and \'
     * escapes), so Bengali text with newlines or semicolons stays intact.
     *
     * @return array<int, string>
     */
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
                __('issues.sys_backups_count') => (string) count(app(\App\Services\BackupService::class)->list()),
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

        $n        = count(app(\App\Services\BackupService::class)->list());
        $checks[] = ['ok' => $n > 0, 'label' => __('issues.hc_backup'), 'hint' => (string) $n];

        $m        = SystemState::maintenance();
        $checks[] = ['ok' => ! $m['on'], 'label' => __('issues.hc_maint'), 'hint' => $m['on'] ? 'ON' : 'OFF'];

        // Cron/scheduler heartbeat (written every minute when cron runs schedule:run)
        $beat = cache()->get('scheduler_heartbeat');
        $beatAt = null;
        if (is_string($beat)) {
            try { $beatAt = \Carbon\Carbon::parse($beat); } catch (\Throwable) { $beatAt = null; }
        }
        $cronOk = $beatAt !== null && $beatAt->gt(now()->subMinutes(10));
        $checks[] = [
            'ok'    => $cronOk,
            'label' => __('issues.hc_cron'),
            'hint'  => $beatAt ? $beatAt->diffForHumans() : __('issues.hc_cron_never'),
        ];

        // Outgoing mail configured (anything other than log driver)
        $mailMode = (string) \App\Support\MailSettings::all()['mode'];
        $checks[] = ['ok' => $mailMode !== 'log', 'label' => __('issues.hc_mail'), 'hint' => $mailMode];

        // Newest backup is fresh (≤ 7 days)
        $bk = app(\App\Services\BackupService::class)->list();
        $fresh = $bk !== [] && $bk[0]['mtime'] >= now()->subDays(7)->timestamp;
        $checks[] = ['ok' => $fresh, 'label' => __('issues.hc_backup_fresh'), 'hint' => $bk !== [] ? $bk[0]['date'] : '—'];

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
