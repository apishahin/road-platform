<?php

namespace App\Http\Controllers;

use App\Models\{Issue, IssueFollow};

class IssueFollowController extends Controller
{
    /** Toggle follow/unfollow for the logged-in user. */
    public function toggle(Issue $issue)
    {
        $user = auth()->user();

        $existing = IssueFollow::where('issue_id', $issue->id)->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();

            return back()->with('status', __('issues.follow_removed'));
        }

        IssueFollow::firstOrCreate(
            ['issue_id' => $issue->id, 'user_id' => $user->id],
            ['created_at' => now()]
        );

        return back()->with('status', __('issues.follow_added'));
    }
}
