<?php

namespace App\Http\Controllers;

use App\Models\{Issue, Observation};
use Illuminate\Http\Request;

class IssueObservationController extends Controller
{
    /**
     * A signed-in citizen says what the problem looks like now.
     * This is only a claim: it never changes the issue status.
     * One answer per user per issue (a new answer replaces the old one).
     */
    public function store(Request $request, string $publicId)
    {
        $issue = Issue::where('public_id', $publicId)->firstOrFail();

        abort_unless(
            $issue->published_at !== null && in_array($issue->disposition, ['active', 'reopened'], true),
            404
        );

        $data = $request->validate([
            'type' => ['required', 'in:fixed,still_exists,got_worse'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        Observation::updateOrCreate(
            ['issue_id' => $issue->id, 'user_id' => $request->user()->id],
            ['type' => $data['type'], 'note' => $data['note'] ?? null]
        );

        return redirect(url('/issues/' . $issue->public_id) . '#confirm')->with('obs_done', true);
    }
}