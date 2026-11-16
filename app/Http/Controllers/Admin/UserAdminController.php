<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\BlockedIp;
use App\Models\AuthorityContact;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAdminController extends Controller
{
    private function guard(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'admin', 403);

        return $u;
    }

    private function relatedAreaIds(AdminArea $area): array
    {
        $ids = [$area->id];

        $p = $area->parent_id;
        $guard = 0;
        while ($p && $guard++ < 8) {
            $ids[] = $p;
            $p = AdminArea::where('id', $p)->value('parent_id');
        }

        $level = [$area->id];
        for ($i = 0; $i < 6 && $level; $i++) {
            $level = AdminArea::whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return array_values(array_unique($ids));
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');
        $areaId = (int) $request->query('area', 0);
        $v = (string) $request->query('v', '');
        $users = User::query()->orderBy('id');

        // Verification-state filter: verified / unverified / no-number.
        if (! in_array($v, ['verified', 'unverified', 'none'], true)) {
            $v = '';
        }
        if ($v === 'verified') {
            $users->whereNotNull('phone')->whereNotNull('phone_verified_at');
        } elseif ($v === 'unverified') {
            $users->whereNotNull('phone')->whereNull('phone_verified_at');
        } elseif ($v === 'none') {
            $users->whereNull('phone');
        }

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

        if (! in_array($role, ['citizen', 'volunteer', 'moderator', 'admin'], true)) {
            $role = '';
        }
        if ($role !== '') {
            $users->where('role', $role);
        }

        $areaLabel = null;
        if ($areaId > 0) {
            $area = AdminArea::with('parent.parent.parent')->find($areaId);
            if ($area) {
                $uids = DB::table('moderator_areas')
                    ->whereNull('revoked_at')
                    ->whereIn('admin_area_id', $this->relatedAreaIds($area))
                    ->pluck('user_id')->all();
                $users->whereIn('id', $uids);
                $areaLabel = AuthorityContact::pathOf($area);
            } else {
                $areaId = 0;
            }
        }

        $page = $users->paginate(30)->withQueryString();

        $grants = DB::table('moderator_areas')
            ->whereNull('revoked_at')
            ->whereIn('user_id', $page->pluck('id')->all())
            ->get(['user_id', 'admin_area_id']);
        $areaModels = AdminArea::with('parent.parent.parent')
            ->whereIn('id', $grants->pluck('admin_area_id')->unique()->all())
            ->get()->keyBy('id');
        $labels = [];
        foreach ($grants as $g) {
            if ($a = $areaModels->get($g->admin_area_id)) {
                $labels[$g->user_id][] = AuthorityContact::pathOf($a);
            }
        }

        return view('admin.users.index', [
            'users' => $page,
            'q' => $q,
            'role' => $role,
            'v' => $v,
            'areaId' => $areaId,
            'areaLabel' => $areaLabel,
            'labels' => $labels,
            'blockedIps' => BlockedIp::orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function areas(Request $request)
    {
        $this->guard($request);
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

    /** Block a user: ban flag (all existing checks apply), kill sessions, block every known IP. */
    public function block(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $user = User::findOrFail($id);

        if ($user->id === $admin->id || $user->role === 'admin') {
            return back()->with('status', __('adminusers.cant'));
        }

        $user->is_banned = true;
        $user->save();

        // Known device IPs come from the database session store.
        $ips = DB::table('sessions')->where('user_id', $user->id)
            ->whereNotNull('ip_address')->pluck('ip_address')->unique();
        foreach ($ips as $ip) {
            BlockedIp::firstOrCreate(
                ['ip' => $ip],
                ['user_id' => $user->id, 'created_by' => $admin->id]
            );
        }
        // Log them out everywhere right away.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        app(AuditService::class)->log($admin, 'user.block', 'user', (string) $user->id, ['ips' => $ips->count()]);

        return back()->with('status', __('adminusers.blocked_msg'));
    }

    /** Unblock: clear the ban and lift that user's IP blocks. */
    public function unblock(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $user = User::findOrFail($id);

        if ($user->id === $admin->id || $user->role === 'admin') {
            return back()->with('status', __('adminusers.cant'));
        }

        $user->is_banned = false;
        $user->save();
        BlockedIp::where('user_id', $user->id)->delete();

        app(AuditService::class)->log($admin, 'user.unblock', 'user', (string) $user->id);

        return back()->with('status', __('adminusers.unblocked_msg'));
    }

    /** Delete: wipe personal identifiers; the row stays so public reports keep an author. */
    public function destroy(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $user = User::findOrFail($id);

        if ($user->id === $admin->id || $user->role === 'admin') {
            return back()->with('status', __('adminusers.cant'));
        }

        app(AuditService::class)->log($admin, 'user.delete', 'user', (string) $user->id);

        DB::table('sessions')->where('user_id', $user->id)->delete();
        BlockedIp::where('user_id', $user->id)->delete();
        $user->forceFill([
            'phone'             => null,
            'phone_verified_at' => null,
            'display_name'      => null,
            'email'             => null,
            'google_id'         => null,
            'password'          => null,
            'remember_token'    => null,
        ])->save();

        return back()->with('status', __('adminusers.deleted_msg'));
    }

    /** Lift one IP block by hand (e.g. shared network caught by mistake). */
    public function unblockIp(Request $request, int $id)
    {
        $admin = $this->guard($request);
        $b = BlockedIp::findOrFail($id);
        $b->delete();

        app(AuditService::class)->log($admin, 'user.unblock_ip', 'blocked_ip', (string) $id, ['ip' => $b->ip]);

        return back()->with('status', __('adminusers.ip_removed'));
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