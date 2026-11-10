<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\AuthorityContact;
use App\Models\Issue;
use App\Models\IssueNotification;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class IssueNotifyController extends Controller
{
    private function guard(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->isModerator(), 403);

        return $u;
    }

    private function canSend(Issue $issue): bool
    {
        return $issue->published_at !== null && $issue->unpublished_at === null;
    }

    private function areaChain(?int $areaId): array
    {
        $ids = [];
        $id = $areaId;
        $guard = 0;
        while ($id && $guard++ < 8) {
            $ids[] = (int) $id;
            $id = AdminArea::where('id', $id)->value('parent_id');
        }

        return $ids;
    }

    private function contactsFor(Issue $issue): Collection
    {
        $ids = $this->areaChain($issue->admin_area_id);
        if (! $ids) {
            return collect();
        }
        $order = array_flip($ids);

        return AuthorityContact::with('area.parent.parent.parent')
            ->where('status', 'approved')
            ->whereIn('admin_area_id', $ids)
            ->orderBy('name')
            ->get()
            ->sortBy(fn ($c) => $order[$c->admin_area_id] ?? 99)
            ->values();
    }

    private function waNumber(?string $n): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $n);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return '88' . $d;
        }

        return $d;
    }

    private function defaultMessage(Issue $issue): string
    {
        $site = (string) config('app.name');
        $areaPath = '';
        if ($issue->admin_area_id) {
            $area = AdminArea::with('parent.parent.parent')->find($issue->admin_area_id);
            $areaPath = $area ? AuthorityContact::pathOf($area) : '';
        }
        $place = implode(', ', array_filter([$issue->road_name, $issue->landmark, $areaPath]));

        $lines = [
            __('notify.tpl_intro'),
            '',
            __('notify.tpl_body', ['site' => $site]),
            '',
            __('notify.tpl_title') . ': ' . $issue->title,
            __('notify.tpl_place') . ': ' . $place,
            __('notify.tpl_link') . ': ' . route('issues.show', $issue->public_id),
            '',
            __('notify.tpl_ask'),
            '',
            __('notify.tpl_sign', ['site' => $site]),
        ];

        return implode("\n", $lines);
    }

    public function show(Request $request, int $issue)
    {
        $this->guard($request);
        $iss = Issue::findOrFail($issue);
        $contacts = $this->contactsFor($iss);
        $wa = [];
        foreach ($contacts as $c) {
            $wa[$c->id] = $this->waNumber($c->whatsapp);
        }

        return view('admin.issues.notify', [
            'issue' => $iss,
            'contacts' => $contacts,
            'waNumbers' => $wa,
            'message' => old('message', $this->defaultMessage($iss)),
            'history' => IssueNotification::with('contact')->where('issue_id', $iss->id)->orderByDesc('id')->limit(30)->get(),
            'canSend' => $this->canSend($iss),
            'mailMode' => (string) config('mail.default'),
        ]);
    }

    public function sendEmail(Request $request, int $issue)
    {
        $actor = $this->guard($request);
        $iss = Issue::findOrFail($issue);
        if (! $this->canSend($iss)) {
            return back()->with('status', __('notify.not_published'));
        }
        if (! $request->filled('contacts')) {
            return back()->withInput()->with('status', __('notify.none_selected'));
        }

        $data = $request->validate([
            'contacts' => 'required|array|min:1|max:50',
            'contacts.*' => 'integer',
            'message' => 'required|string|max:3000',
        ]);

        $allowed = $this->contactsFor($iss)->keyBy('id');
        $subject = __('notify.subject', ['title' => $iss->title]);
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach (array_unique($data['contacts']) as $cid) {
            $c = $allowed->get((int) $cid);
            if (! $c || ! $c->email) {
                continue;
            }

            $recent = IssueNotification::where('issue_id', $iss->id)
                ->where('authority_contact_id', $c->id)
                ->where('channel', 'email')
                ->where('status', 'sent')
                ->where('created_at', '>=', now()->subDay())
                ->exists();
            if ($recent) {
                $skipped++;
                continue;
            }

            $status = config('mail.default') === 'log' ? 'logged' : 'sent';
            $error = null;
            try {
                Mail::raw($data['message'], function ($m) use ($c, $subject) {
                    $m->to($c->email, $c->name)->subject($subject);
                });
                $sent++;
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = mb_substr($e->getMessage(), 0, 200);
                $failed++;
            }

            IssueNotification::create([
                'issue_id' => $iss->id,
                'authority_contact_id' => $c->id,
                'channel' => 'email',
                'status' => $status,
                'sent_by' => $actor->id,
                'message' => $data['message'],
                'error' => $error,
            ]);
            app(AuditService::class)->log($actor, 'issue.notify', 'issue', (string) $iss->id, [
                'channel' => 'email',
                'contact_id' => $c->id,
                'status' => $status,
            ]);
        }

        if ($sent + $skipped + $failed === 0) {
            return back()->withInput()->with('status', __('notify.none_selected'));
        }

        $msg = config('mail.default') === 'log' ? __('notify.logged', ['n' => $sent]) : __('notify.sent', ['n' => $sent]);
        if ($skipped) {
            $msg .= ' ' . __('notify.skipped', ['n' => $skipped]);
        }
        if ($failed) {
            $msg .= ' ' . __('notify.failed', ['n' => $failed]);
        }

        return back()->with('status', $msg);
    }

    public function whatsapp(Request $request, int $issue, int $contact)
    {
        $actor = $this->guard($request);
        $iss = Issue::findOrFail($issue);
        if (! $this->canSend($iss)) {
            return back()->with('status', __('notify.not_published'));
        }

        $c = $this->contactsFor($iss)->firstWhere('id', $contact);
        $num = $c ? $this->waNumber($c->whatsapp) : null;
        abort_unless($c && $num, 404);

        $text = trim((string) $request->input('message', ''));
        if ($text === '') {
            $text = $this->defaultMessage($iss);
        }
        $text = mb_substr($text, 0, 3000);

        IssueNotification::create([
            'issue_id' => $iss->id,
            'authority_contact_id' => $c->id,
            'channel' => 'whatsapp',
            'status' => 'opened',
            'sent_by' => $actor->id,
            'message' => $text,
        ]);
        app(AuditService::class)->log($actor, 'issue.notify', 'issue', (string) $iss->id, [
            'channel' => 'whatsapp',
            'contact_id' => $c->id,
        ]);

        return redirect()->away('https://wa.me/' . $num . '?text=' . rawurlencode($text));
    }
}