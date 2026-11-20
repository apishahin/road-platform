<?php

namespace App\Console\Commands;

use App\Models\{Issue, Report, User};
use App\Support\DigestSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyDigest extends Command
{
    protected $signature = 'digest:weekly';
    protected $description = 'Email a weekly activity summary to the configured admin addresses.';

    public function handle(): int
    {
        $s = DigestSettings::all();
        if (! $s['on'] || $s['emails'] === []) {
            $this->info('Digest disabled or no recipients.');

            return self::SUCCESS;
        }

        $since = now()->subDays(7);

        $newIssues   = Issue::whereNotNull('published_at')->where('created_at', '>=', $since)->count();
        $newReports  = Report::where('created_at', '>=', $since)->count();
        $repaired    = Issue::whereNotNull('published_at')->where('progress_status', 'repaired')
            ->where('last_activity_at', '>=', $since)->count();
        $pending     = Report::where('moderation', 'pending')->count();
        $newUsers    = User::where('created_at', '>=', $since)->count();
        $totalOpen   = Issue::whereNotNull('published_at')->where('disposition', 'active')
            ->where('progress_status', '!=', 'repaired')->count();

        $body = implode("\n", [
            __('issues.digest_subject'),
            '',
            __('issues.digest_new_issues', ['n' => $newIssues]),
            __('issues.digest_new_reports', ['n' => $newReports]),
            __('issues.digest_repaired', ['n' => $repaired]),
            __('issues.digest_pending', ['n' => $pending]),
            __('issues.digest_new_users', ['n' => $newUsers]),
            __('issues.digest_open', ['n' => $totalOpen]),
            '',
            url('/admin'),
        ]);

        try {
            Mail::raw($body, function ($m) use ($s) {
                $m->to($s['emails'])->subject(__('issues.digest_subject'));
            });
            $this->info('Digest sent to ' . count($s['emails']) . ' recipient(s).');
        } catch (\Throwable $e) {
            report($e);
            $this->error('Digest failed: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
