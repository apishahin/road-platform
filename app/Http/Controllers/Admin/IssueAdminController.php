<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Services\IssuePublishService;
use Illuminate\Http\Request;

class IssueAdminController extends Controller
{
    public function __construct(private IssuePublishService $service)
    {
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isModerator(), 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        $tab = $request->query('tab') === 'unpublished' ? 'unpublished' : 'published';
        $q = Issue::query();
        if ($tab === 'published') {
            $q->whereNotNull('published_at')->orderByDesc('published_at');
        } else {
            $q->whereNull('published_at')->whereNotNull('unpublished_at')->orderByDesc('unpublished_at');
        }

        return view('admin.issues.index', [
            'issues' => $q->paginate(20)->withQueryString(),
            'tab' => $tab,
        ]);
    }

    public function unpublish(Request $request, Issue $issue)
    {
        $this->guard($request);
        $data = $request->validate(['reason' => ['required', 'in:privacy,wrong,duplicate,other']]);
        $ok = $this->service->unpublish($issue, $request->user(), $data['reason']);

        return back()->with('status', __($ok ? 'issueadmin.unpublished_msg' : 'issueadmin.not_allowed'));
    }

    public function republish(Request $request, Issue $issue)
    {
        $this->guard($request);
        $ok = $this->service->republish($issue, $request->user());

        return back()->with('status', __($ok ? 'issueadmin.republished_msg' : 'issueadmin.not_allowed'));
    }

    public function edit(Request $request, Issue $issue)
    {
        $this->guard($request);

        return view('admin.issues.edit', ['issue' => $issue]);
    }

    public function update(Request $request, Issue $issue)
    {
        $this->guard($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'road_name' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ]);
        $changed = $this->service->update($issue, $request->user(), $data);

        return redirect()->route('admin.issues.index')
            ->with('status', __($changed ? 'issueadmin.saved_msg' : 'issueadmin.nochange_msg'));
    }
}