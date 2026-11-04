<?php

namespace App\Support;

use App\Models\Evidence;
use App\Models\Issue;
use App\Models\Report;
use App\Services\AuditService;

/**
 * Registered from AppServiceProvider::boot().
 *  - remembers which disk each photo is on
 *  - writes an audit event whenever a report's moderation state changes
 */
class PlatformHooks
{
    public static function register(): void
    {
        Evidence::creating(function (Evidence $e) {
            if (! $e->private_disk) {
                $e->private_disk = StorageSettings::privateDisk();
            }
        });

        Evidence::saving(function (Evidence $e) {
            if ($e->moderation === 'approved') {
                if ($e->isDirty('moderation')) {
                    $e->public_disk = StorageSettings::publicDisk();
                }
            } else {
                if ($e->exists && $e->isDirty('moderation')) {
                    // photo was moved back to the private disk
                    $e->private_disk = StorageSettings::privateDisk();
                }
                $e->public_disk = null;
            }
        });

        Report::updated(function (Report $report) {
            if (! $report->wasChanged('moderation')) {
                return;
            }

            app(AuditService::class)->log(
                auth()->user(),
                'report.' . $report->moderation,
                'report',
                (string) $report->public_id,
                [
                    'from'  => $report->getOriginal('moderation'),
                    'to'    => $report->moderation,
                    'issue' => Issue::whereKey($report->issue_id)->value('public_id'),
                ]
            );
        });
    }
}