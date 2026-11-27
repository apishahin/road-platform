<?php

namespace App\Http\Controllers;

use App\Models\{Issue, StatusEvent};
use App\Support\{IssuePresenter, StatusLabel};
use Illuminate\Support\Str;

class IssueController extends Controller
{
    /** Redirect pre-Patch66 public ULID links to the compact numeric canonical URL. */
    public function legacy(Issue $issue)
    {
        if ($issue->published_at === null || $issue->needsLocationReview()) {
            abort_unless(auth()->user()?->isModerator(), 404);
        }

        return redirect()->to(IssuePresenter::url($issue), 301);
    }

    public function show(Issue $issue)
    {
        // Unpublished and location-review issues are visible only to moderators.
        if ($issue->published_at === null || $issue->needsLocationReview()) {
            abort_unless(auth()->user()?->isModerator(), 404);
        }
        $issue->load(['category', 'adminArea', 'duplicateOf']);

        // Timeline: oldest first. Actor names are never shown, only the role.
        // Stored reasons are never edited; StatusLabel::reason() only translates
        // known system-written text at display time.
        $timeline = StatusEvent::query()
            ->where('issue_id', $issue->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (StatusEvent $e) => [
                'at'     => $this->formatTime($e->created_at),
                'label'  => StatusLabel::forEvent($e),
                'reason' => StatusLabel::reason($e->reason),
                'source' => StatusLabel::source($e->source_type),
                'actor'  => StatusLabel::actor($e->actor_type),
                'url'    => $this->safeUrl($e->source_url),
            ]);

        // Only moderator-approved reports and photos are public
        $reports = $issue->reports()
            ->where('moderation', 'approved')
            ->limit(20)
            ->get();

        $photos = $issue->evidence()
            ->where('evidence.moderation', 'approved')
            ->orderByDesc('evidence.created_at')
            ->get();

        $authorities = $issue->authorityLinks()->with('authority')->get();

        $notified = \App\Models\IssueNotification::with('contact:id,position')
            ->where('issue_id', $issue->id)
            ->where('status', 'sent')
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($n) => [
                'date'     => IssuePresenter::num($n->created_at ? $n->created_at->format('d/m/Y') : ''),
                'position' => trim((string) ($n->contact->position ?? '')),
            ]);

        $extra = $this->glance($issue);

        $__u = auth()->user();
        $isFollowing = $__u
            ? \App\Models\IssueFollow::where('issue_id', $issue->id)->where('user_id', $__u->id)->exists()
            : false;

        return view('issues.show', $extra + [
            'isFollowing'    => $isFollowing,
            'followersCount' => \App\Models\IssueFollow::where('issue_id', $issue->id)->count(),
            'notified'      => $notified,
            'issue'        => $issue,
            'status'       => IssuePresenter::display($issue),
            'explanation'  => StatusLabel::explain($issue),
            'timeline'     => $timeline,
            'reports'      => $reports,
            'photos'       => $photos,
            'authorities'  => $authorities,
            'reportsCount' => IssuePresenter::num($issue->reports_count),
            'firstAt'      => IssuePresenter::num($issue->first_reported_at?->format('d/m/Y') ?? '—'),
            'lastAt'       => IssuePresenter::num($issue->last_activity_at?->diffForHumans() ?? '—'),
        ]);
    }

    /**
     * Date and time for the timeline.
     * Bangla: time of day in words (ভোর, সকাল, দুপুর, বিকাল, সন্ধ্যা, রাত) instead of AM/PM.
     * Other languages: the usual AM/PM.
     */
    private function formatTime(?\DateTimeInterface $time): string
    {
        if ($time === null) {
            return '—';
        }

        if (app()->getLocale() !== 'bn') {
            return IssuePresenter::num($time->format('d/m/Y, h:i A'));
        }

        $hour = (int) $time->format('G');
        $period = match (true) {
            $hour >= 4 && $hour < 6   => 'ভোর',
            $hour >= 6 && $hour < 12  => 'সকাল',
            $hour >= 12 && $hour < 15 => 'দুপুর',
            $hour >= 15 && $hour < 18 => 'বিকাল',
            $hour >= 18 && $hour < 20 => 'সন্ধ্যা',
            default                   => 'রাত',
        };

        return IssuePresenter::num($time->format('d/m/Y') . ', ' . $period . ' ' . $time->format('h:i'));
    }

    /** Only allow http(s) links from source fields, to block javascript: links. */
    private function safeUrl(?string $url): ?string
    {
        return $url && Str::startsWith($url, ['https://', 'http://']) ? $url : null;
    }

    /**
     * Plain-language summary for the "simple" issue page.
     * Only roles, counts and dates are used: never names, phones or notes.
     */
    private function glance(Issue $issue): array
    {
        $date = fn ($d) => $d ? IssuePresenter::num($d->format('d/m/Y')) : null;

        $sent = \App\Models\IssueNotification::with('contact:id,position')
            ->where('issue_id', $issue->id)
            ->where('status', 'sent')
            ->orderBy('created_at')
            ->first();

        $channel = '';
        if ($sent && in_array($sent->channel, ['email', 'whatsapp', 'sms'], true)) {
            $channel = __('issues.glance_ch_' . $sent->channel);
        }

        $counts = \App\Models\Observation::query()
            ->where('issue_id', $issue->id)
            ->selectRaw('type, count(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type');

        $progressDate = function (string $to) use ($issue, $date) {
            $e = StatusEvent::query()
                ->where('issue_id', $issue->id)
                ->where('dimension', 'progress')
                ->where('to_value', $to)
                ->orderByDesc('id')
                ->first();

            return $e ? $date($e->created_at) : null;
        };

        $mine = auth()->check()
            ? \App\Models\Observation::where('issue_id', $issue->id)->where('user_id', auth()->id())->value('type')
            : null;

        return [
            'issueView' => \App\Support\AppearanceSettings::all()['issue_view'] ?? 'simple',
            'glance'    => [
                'notified_date'     => $sent ? $date($sent->created_at) : null,
                'notified_channel'  => $channel,
                'notified_position' => trim((string) ($sent?->contact?->position ?? '')),
                'fixed'             => (int) ($counts['fixed'] ?? 0),
                'still'             => (int) ($counts['still_exists'] ?? 0),
                'worse'             => (int) ($counts['got_worse'] ?? 0),
                'claim_date'        => $progressDate('completion_reported'),
                'repaired'          => $issue->progress_status === 'repaired',
                'repaired_date'     => $progressDate('repaired'),
                'mine'              => $mine,
                'who'               => $this->glanceWho($issue),
                'canVerify'         => \App\Http\Controllers\IssueVerifyController::canVerify(auth()->user(), $issue),
            ],
        ];
    }

    /**
     * Who reported and who visited the place: only roles and counts, never people.
     */
    private function glanceWho(Issue $issue): array
    {
        $rows = \App\Models\Report::query()
            ->join('users', 'users.id', '=', 'reports.user_id')
            ->where('reports.issue_id', $issue->id)
            ->where('reports.moderation', 'approved')
            ->selectRaw('users.role as r, count(distinct reports.user_id) as c')
            ->groupBy('users.role')
            ->pluck('c', 'r');

        $reporters = ['citizen' => 0, 'volunteer' => 0, 'moderator' => 0];
        foreach ($rows as $role => $n) {
            $key = $role === 'admin' ? 'moderator' : $role;
            if (array_key_exists($key, $reporters)) {
                $reporters[$key] += (int) $n;
            }
        }

        $visits = StatusEvent::query()
            ->where('issue_id', $issue->id)
            ->where('dimension', 'verification')
            ->where('source_type', 'site_visit')
            ->whereIn('to_value', ['reviewed', 'verified'])
            ->whereIn('actor_type', ['volunteer', 'moderator'])
            ->selectRaw('actor_type as r, count(distinct actor_id) as c, max(created_at) as d')
            ->groupBy('actor_type')
            ->get();

        $roles = [];
        $latest = null;
        foreach ($visits as $v) {
            $roles[$v->r] = (int) $v->c;
            if ($v->d && ($latest === null || $v->d > $latest)) {
                $latest = $v->d;
            }
        }

        return [
            'reporters' => $reporters,
            'site'      => [
                'roles' => $roles,
                'date'  => $latest ? IssuePresenter::num(\Illuminate\Support\Carbon::parse($latest)->format('d/m/Y')) : null,
            ],
        ];
    }
}
