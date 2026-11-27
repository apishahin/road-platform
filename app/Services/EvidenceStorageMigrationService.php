<?php

namespace App\Services;

use App\Models\Evidence;
use App\Support\StorageSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Safely copies evidence between the local and R2 disk pairs.
 *
 * A database pointer is changed only after the destination object exists and
 * has the same byte count as the source. The source is deliberately retained:
 * this gives an administrator a rollback copy and means a transient cleanup
 * failure can never make a photo unavailable.
 */
class EvidenceStorageMigrationService
{
    /**
     * Counts are record counts, not object counts. A single approved evidence
     * row can require both a private-original and a public-processed copy.
     */
    public function summary(string $target): array
    {
        $this->assertTarget($target);
        $disks = StorageSettings::OPTIONS[$target];

        $privateRemaining = Evidence::query()
            ->where(function (Builder $query) use ($disks) {
                $query->whereNull('private_disk')
                    ->orWhere('private_disk', '!=', $disks['private']);
            })
            ->count();

        $publicRemaining = Evidence::query()
            ->where('moderation', 'approved')
            ->where(function (Builder $query) use ($disks) {
                $query->whereNull('public_disk')
                    ->orWhere('public_disk', '!=', $disks['public']);
            })
            ->count();

        $legacy = Evidence::query()
            ->where(function (Builder $query) {
                $query->whereNull('private_disk')
                    ->orWhere(function (Builder $approved) {
                        $approved->where('moderation', 'approved')->whereNull('public_disk');
                    });
            })
            ->count();

        return [
            'total' => Evidence::query()->count(),
            'remaining' => $this->candidates($target)->count(),
            'private_remaining' => $privateRemaining,
            'public_remaining' => $publicRemaining,
            'legacy' => $legacy,
        ];
    }

    /**
     * Copies one bounded batch. It intentionally does not delete the source
     * objects; the old disk remains a verified fallback after a switch.
     *
     * @return array{target:string,attempted:int,migrated:int,partial:int,failed:int,files_copied:int,issues:array<int,array<string,mixed>>,remaining:int}
     */
    public function migrate(string $target, int $limit = 25): array
    {
        $this->assertTarget($target);
        $limit = max(1, min(100, $limit));
        $disks = StorageSettings::OPTIONS[$target];

        $rows = $this->candidates($target)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $result = [
            'target' => $target,
            'attempted' => $rows->count(),
            'migrated' => 0,
            'partial' => 0,
            'failed' => 0,
            'files_copied' => 0,
            'issues' => [],
            'remaining' => 0,
        ];

        foreach ($rows as $evidence) {
            $privateNeeded = $evidence->private_disk !== $disks['private'];
            $publicNeeded = $evidence->moderation === 'approved'
                && $evidence->public_disk !== $disks['public'];

            $private = ['ok' => true, 'copied' => 0, 'issues' => []];
            $public = ['ok' => true, 'copied' => 0, 'issues' => []];

            if ($privateNeeded) {
                $private = $this->copyGroup(
                    (string) ($evidence->private_disk ?: StorageSettings::privateDisk()),
                    $disks['private'],
                    $this->privatePaths($evidence),
                    (int) $evidence->id,
                    'private'
                );
            }

            if ($publicNeeded) {
                $public = $this->copyGroup(
                    (string) ($evidence->public_disk ?: StorageSettings::publicDisk()),
                    $disks['public'],
                    $this->publicPaths($evidence),
                    (int) $evidence->id,
                    'public'
                );
            }

            $result['files_copied'] += $private['copied'] + $public['copied'];
            $result['issues'] = array_merge($result['issues'], $private['issues'], $public['issues']);

            $updates = [];
            if ($privateNeeded && $private['ok']) {
                $updates['private_disk'] = $disks['private'];
            }
            if ($publicNeeded && $public['ok']) {
                $updates['public_disk'] = $disks['public'];
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('evidence')->where('id', $evidence->id)->update($updates);
            }

            if ($private['ok'] && $public['ok']) {
                $result['migrated']++;
            } elseif ($updates !== []) {
                // An approved public copy may still be made available even if
                // its older private original is missing, but the row remains
                // visibly incomplete until the other disk pointer is fixed.
                $result['partial']++;
            } else {
                $result['failed']++;
            }
        }

        // Keep the session payload small while still showing the administrator
        // actionable file/disk information for manual remediation.
        $result['issues'] = array_slice($result['issues'], 0, 12);
        $result['remaining'] = $this->summary($target)['remaining'];

        return $result;
    }

    private function candidates(string $target): Builder
    {
        $disks = StorageSettings::OPTIONS[$target];

        return Evidence::query()->where(function (Builder $query) use ($disks) {
            $query->where(function (Builder $private) use ($disks) {
                $private->whereNull('private_disk')
                    ->orWhere('private_disk', '!=', $disks['private']);
            })->orWhere(function (Builder $public) use ($disks) {
                $public->where('moderation', 'approved')
                    ->where(function (Builder $disk) use ($disks) {
                        $disk->whereNull('public_disk')
                            ->orWhere('public_disk', '!=', $disks['public']);
                    });
            });
        });
    }

    /** @return array<int, string> */
    private function privatePaths(Evidence $evidence): array
    {
        $paths = [$evidence->private_path];

        // Before approval the processed copy is private too. Once approved,
        // that copy is handled by publicPaths() on the public disk instead.
        if ($evidence->moderation !== 'approved') {
            $paths[] = $evidence->public_path;
        }

        return array_values(array_unique(array_filter($paths, fn ($path) => is_string($path) && $path !== '')));
    }

    /** @return array<int, string> */
    private function publicPaths(Evidence $evidence): array
    {
        if ($evidence->moderation !== 'approved') {
            return [];
        }

        return array_values(array_unique(array_filter([$evidence->public_path], fn ($path) => is_string($path) && $path !== '')));
    }

    /**
     * @param  array<int, string>  $paths
     * @return array{ok:bool,copied:int,issues:array<int,array<string,mixed>>}
     */
    private function copyGroup(string $from, string $to, array $paths, int $evidenceId, string $kind): array
    {
        $result = ['ok' => true, 'copied' => 0, 'issues' => []];

        foreach ($paths as $path) {
            $copy = $this->copyAndVerify($from, $to, $path);
            if ($copy['ok']) {
                $result['copied'] += $copy['copied'] ? 1 : 0;

                continue;
            }

            $result['ok'] = false;
            $result['issues'][] = [
                'evidence_id' => $evidenceId,
                'kind' => $kind,
                'from' => $from,
                'to' => $to,
                'path' => $path,
                'reason' => $copy['reason'],
            ];

            // Do not move this disk pointer when even one object belonging to
            // it is missing or cannot be verified at the destination.
            break;
        }

        return $result;
    }

    /** @return array{ok:bool,copied:bool,reason:string} */
    private function copyAndVerify(string $from, string $to, string $path): array
    {
        try {
            $source = Storage::disk($from);
            $destination = Storage::disk($to);

            if (! $source->exists($path)) {
                return ['ok' => false, 'copied' => false, 'reason' => 'উৎস ডিস্কে ফাইলটি পাওয়া যায়নি'];
            }

            if ($from === $to) {
                // This is a legacy-pointer backfill. The source object itself
                // is the verified target, so no duplicate object is needed.
                return ['ok' => true, 'copied' => false, 'reason' => ''];
            }

            $sourceSize = $source->size($path);
            $stream = $source->readStream($path);
            if (! is_resource($stream)) {
                return ['ok' => false, 'copied' => false, 'reason' => 'উৎস ফাইলটি stream হিসেবে পড়া যায়নি'];
            }

            try {
                $written = $destination->put($path, $stream);
            } finally {
                fclose($stream);
            }

            if ($written === false || ! $destination->exists($path)) {
                return ['ok' => false, 'copied' => false, 'reason' => 'গন্তব্য ডিস্কে কপি যাচাই করা যায়নি'];
            }

            if ($destination->size($path) !== $sourceSize) {
                return ['ok' => false, 'copied' => false, 'reason' => 'গন্তব্য কপির আকার উৎস ফাইলের সঙ্গে মেলেনি'];
            }

            return ['ok' => true, 'copied' => true, 'reason' => ''];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'copied' => false, 'reason' => 'কপি বা যাচাই ব্যর্থ হয়েছে: '.class_basename($e)];
        }
    }

    private function assertTarget(string $target): void
    {
        if (! isset(StorageSettings::OPTIONS[$target])) {
            throw new \InvalidArgumentException('Storage target must be local or r2.');
        }
    }
}
