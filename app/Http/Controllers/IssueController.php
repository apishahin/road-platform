<?php

namespace App\Http\Controllers;

use App\Models\{Issue, StatusEvent};
use App\Support\{IssuePresenter, StatusLabel};
use Illuminate\Support\Str;

class IssueController extends Controller
{
    public function show(Issue $issue)
    {
        // Unpublished issues are visible only to moderators.
        if ($issue->published_at === null) {
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

        // Small OpenStreetMap embed around the issue point
        $lat = (float) $issue->lat;
        $lng = (float) $issue->lng;
        $pad = 0.004;
        $mapEmbed = 'https://www.openstreetmap.org/export/embed.html?bbox='
            . implode(',', [$lng - $pad, $lat - $pad, $lng + $pad, $lat + $pad])
            . '&layer=mapnik&marker=' . $lat . ',' . $lng;

        $extra = $this->glance($issue);

        return view('issues.show', $extra + [
            'notified'      => $notified,
            'issue'        => $issue,
            'status'       => IssuePresenter::display($issue),
            'explanation'  => StatusLabel::explain($issue),
            'timeline'     => $timeline,
            'reports'      => $reports,
            'photos'       => $photos,
            'authorities'  => $authorities,
            'mapEmbed'     => $mapEmbed,
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
            ],
        ];
    }
}
