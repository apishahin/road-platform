<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    private function guard(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'admin', 403);

        return $u;
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $q = trim((string) $request->query('q', ''));
        $users = User::query()->orderBy('id');

        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q);
            $users->where(function ($w) use ($q, $digits) {
                $w->where('display_name', 'like', '%' . $q . '%');
                if ($digits !== '') {
                    $w->orWhere('phone', 'like', '%' . $digits . '%');
                }
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
            });
        }

        return view('admin.users.index', [
            'users' => $users->paginate(30)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function setRole(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $data = $request->validate(['role' => 'required|in:citizen,volunteer,moderator']);
        $user = User::findOrFail($id);

        if ($user->id === $admin->id || ! in_array($user->role, ['citizen', 'volunteer', 'moderator'], true)) {
            return back()->with('status', __('adminusers.cant'));
        }

        $from = $user->role;
        $to = $data['role'];

        if ($from !== $to) {
            $user->role = $to;
            $user->save();
            app(AuditService::class)->log($admin, 'user.role', 'user', (string) $user->id, ['from' => $from, 'to' => $to]);
        }

        return back()->with('status', __('adminusers.changed'));
    }
}