<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;

/** Admin → Audit log: who changed what, when. Read-only. */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $events = AuditEvent::with('actor:id,phone,display_name')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log', ['events' => $events]);
    }
}
