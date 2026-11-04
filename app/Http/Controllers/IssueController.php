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

        // Small OpenStreetMap embed around the issue point
        $lat = (float) $issue->lat;
        $lng = (float) $issue->lng;
        $pad = 0.004;
        $mapEmbed = 'https://www.openstreetmap.org/export/embed.html?bbox='
            . implode(',', [$lng - $pad, $lat - $pad, $lng + $pad, $lat + $pad])
            . '&layer=mapnik&marker=' . $lat . ',' . $lng;

        return view('issues.show', [
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
}