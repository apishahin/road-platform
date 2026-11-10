<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\AuthorityContact;
use Illuminate\Http\Request;

class IssueAreaSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = $request->user();
        abort_unless($u && $u->isModerator(), 403);

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $rows = AdminArea::with('parent.parent.parent')
            ->where(function ($w) use ($like) {
                $w->where('name_bn', 'like', $like)->orWhere('name_en', 'like', $like);
            })
            ->orderBy('level')->limit(20)->get();

        return response()->json($rows->map(fn ($a) => [
            'id' => $a->id,
            'label' => AuthorityContact::pathOf($a),
        ])->values());
    }
}