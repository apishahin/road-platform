<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ModerationController extends Controller
{
    public function __construct(private ModerationService $moderation)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeModerator($request);

        $reports = Report::with(['issue.category', 'evidence'])
            ->where('moderation', 'pending')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return view('admin.moderation', [
            'reports'      => $reports,
            'pendingTotal' => Report::where('moderation', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, Report $report)
    {
        $this->authorizeModerator($request);
        $this->moderation->approve($report, $request->user());

        return back()->with('status', __('report.mod_approved_msg'));
    }

    public function hide(Request $request, Report $report)
    {
        $this->authorizeModerator($request);
        $this->moderation->hide($report, $request->user());

        return back()->with('status', __('report.mod_hidden_msg'));
    }

    /** Moderators can view pending photos from private storage. */
    public function photo(Request $request, Evidence $evidence)
    {
        $this->authorizeModerator($request);

        $disk = $evidence->moderation === 'approved' ? ($evidence->public_disk ?: \App\Support\StorageSettings::publicDisk()) : ($evidence->private_disk ?: \App\Support\StorageSettings::privateDisk());
        abort_unless(Storage::disk($disk)->exists($evidence->public_path), 404);

        return Storage::disk($disk)->response($evidence->public_path, null, [
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeModerator(Request $request): void
    {
        abort_unless($request->user()?->isModerator(), 403);
    }
}