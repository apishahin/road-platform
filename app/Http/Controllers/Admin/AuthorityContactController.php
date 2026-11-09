<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\AuthorityContact;
use App\Services\AuditService;
use App\Support\ModerationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthorityContactController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    private function guard(Request $r): void
    {
        abort_unless($r->user()?->isModerator(), 403);
    }

    private function adminOnly(Request $r): void
    {
        abort_unless($r->user()?->role === 'admin', 403);
    }

    private function clean(?string $v): ?string
    {
        $v = preg_replace('/[^0-9+]/', '', (string) $v);

        return $v === '' ? null : $v;
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $status = in_array($request->query('status'), ['pending', 'approved', 'disabled'], true)
            ? $request->query('status') : null;

        $q = AuthorityContact::with(['area.parent.parent.parent','addedBy'])->orderByDesc('id');
        if (! $isAdmin) {
            $q->where('added_by', $user->id);
        }
        if ($status) {
            $q->where('status', $status);
        }

        return view('admin.contacts.index', [
            'contacts' => $q->paginate(30)->withQueryString(),
            'status' => $status,
            'isAdmin' => $isAdmin,
            'pendingCount' => $isAdmin ? AuthorityContact::where('status', 'pending')->count() : 0,
        ]);
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $user = $request->user();
        $data = $request->validate([
            'admin_area_id' => ['required', 'integer', 'exists:admin_areas,id'],
            'name' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
        ], ['admin_area_id.required' => __('authcontact.no_area')]);

        $phone = $this->clean($data['phone'] ?? null);
        $whatsapp = $this->clean($data['whatsapp'] ?? null);
        $email = ! empty($data['email']) ? $data['email'] : null;
        if (! $phone && ! $whatsapp && ! $email) {
            return back()->withInput()->withErrors(['phone' => __('authcontact.need_one')]);
        }

        ModerationScope::authorizeArea($user, (int) $data['admin_area_id']);

        $isAdmin = $user->role === 'admin';
        $c = AuthorityContact::create([
            'admin_area_id' => (int) $data['admin_area_id'],
            'name' => trim($data['name']),
            'position' => ! empty($data['position']) ? trim($data['position']) : null,
            'phone' => $phone,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'status' => $isAdmin ? 'approved' : 'pending',
            'added_by' => $user->id,
            'approved_by' => $isAdmin ? $user->id : null,
            'approved_at' => $isAdmin ? now() : null,
        ]);
        $this->audit->log($user, 'authority_contact.add', 'authority_contact', (string) $c->id, [
            'area_id' => $c->admin_area_id,
            'status' => $c->status,
        ]);

        return redirect()->route('admin.contacts.index')
            ->with('status', __($isAdmin ? 'authcontact.added_ok' : 'authcontact.added_pending'));
    }

    private function setStatus(Request $request, AuthorityContact $contact, string $to, string $action, string $msg)
    {
        $this->adminOnly($request);
        if ($contact->status !== $to) {
            $contact->forceFill([
                'status' => $to,
                'approved_by' => $to === 'approved' ? $request->user()->id : $contact->approved_by,
                'approved_at' => $to === 'approved' ? now() : $contact->approved_at,
            ])->save();
            $this->audit->log($request->user(), $action, 'authority_contact', (string) $contact->id, [
                'area_id' => $contact->admin_area_id,
            ]);
        }

        return back()->with('status', __($msg));
    }

    public function approve(Request $request, AuthorityContact $contact)
    {
        return $this->setStatus($request, $contact, 'approved', 'authority_contact.approve', 'authcontact.approved_msg');
    }

    public function disable(Request $request, AuthorityContact $contact)
    {
        return $this->setStatus($request, $contact, 'disabled', 'authority_contact.disable', 'authcontact.disabled_msg');
    }

    public function enable(Request $request, AuthorityContact $contact)
    {
        return $this->setStatus($request, $contact, 'approved', 'authority_contact.enable', 'authcontact.enabled_msg');
    }

    public function areaSearch(Request $request)
    {
        $this->guard($request);
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $rows = AdminArea::with('parent.parent.parent')
            ->where(function ($w) use ($like) {
                $w->where('name_bn', 'like', $like)->orWhere('name_en', 'like', $like);
            })
            ->orderBy('level')->orderBy('name_bn')->limit(20)->get();

        return response()->json($rows->map(fn ($a) => [
            'id' => $a->id,
            'label' => AuthorityContact::pathOf($a),
        ])->values());
    }

    public function template(Request $request)
    {
        $this->adminOnly($request);
        $id = AdminArea::where('level', 'division')->value('id') ?? 1;

        return response()->streamDownload(function () use ($id) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['area_id', 'name', 'position', 'phone', 'email', 'whatsapp'], ',', '"', '');
            fputcsv($o, [$id, '[Demo] Divisional Commissioner', 'Commissioner', '01700000001', 'demo1@example.com', '01700000001'], ',', '"', '');
            fputcsv($o, [$id, '[Demo] Deputy Commissioner', 'DC', '01700000002', 'demo2@example.com', ''], ',', '"', '');
            fclose($o);
        }, 'authority-contacts-demo.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function areasCsv(Request $request)
    {
        $this->adminOnly($request);

        return response()->streamDownload(function () {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['area_id', 'level', 'area_name'], ',', '"', '');
            AdminArea::with('parent.parent.parent')->chunkById(500, function ($rows) use ($o) {
                foreach ($rows as $a) {
                    fputcsv($o, [$a->id, $a->level, AuthorityContact::pathOf($a)], ',', '"', '');
                }
            });
            fclose($o);
        }, 'area-ids.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $this->adminOnly($request);
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimes:csv,txt']]);
        $user = $request->user();

        $h = fopen($request->file('file')->getRealPath(), 'r');
        $header = null;
        $row = 0;
        $ok = 0;
        $skip = 0;
        $errors = [];

        while (($cols = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $row++;
            if ($row === 1) {
                $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cols[0] ?? ''));
                $header = array_map(fn ($c) => strtolower(trim((string) $c)), $cols);
                if (! in_array('area_id', $header, true) || ! in_array('name', $header, true)) {
                    fclose($h);

                    return back()->with('import_errors', [__('authcontact.bad_header')]);
                }
                continue;
            }
            if ($row > 1001) {
                $errors[] = __('authcontact.too_many');
                break;
            }
            if (! array_filter($cols, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }
            if (! mb_check_encoding(implode('', array_map('strval', $cols)), 'UTF-8')) {
                $skip++;
                if (count($errors) < 10) {
                    $errors[] = "#$row: " . __('authcontact.bad_encoding');
                }
                continue;
            }

            $rec = [];
            foreach ($header as $i => $k) {
                $rec[$k] = trim((string) ($cols[$i] ?? ''));
            }
            $phone = $this->clean($rec['phone'] ?? null);
            $whatsapp = $this->clean($rec['whatsapp'] ?? null);
            $email = ($rec['email'] ?? '') !== '' ? $rec['email'] : null;

            $v = Validator::make([
                'area_id' => $rec['area_id'] ?? '',
                'name' => $rec['name'] ?? '',
                'position' => $rec['position'] ?? '',
                'email' => $email,
            ], [
                'area_id' => ['required', 'integer', 'exists:admin_areas,id'],
                'name' => ['required', 'max:150'],
                'position' => ['nullable', 'max:150'],
                'email' => ['nullable', 'email', 'max:150'],
            ]);
            if ($v->fails() || (! $phone && ! $whatsapp && ! $email)) {
                $skip++;
                if (count($errors) < 10) {
                    $errors[] = "#$row: " . ($v->fails() ? $v->errors()->first() : __('authcontact.need_one'));
                }
                continue;
            }

            $areaId = (int) $rec['area_id'];
            $dup = AuthorityContact::where('admin_area_id', $areaId)
                ->where(function ($q) use ($phone, $email, $whatsapp) {
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                    if ($whatsapp) {
                        $q->orWhere('whatsapp', $whatsapp);
                    }
                })->exists();
            if ($dup) {
                $skip++;
                continue;
            }

            AuthorityContact::create([
                'admin_area_id' => $areaId,
                'name' => $rec['name'],
                'position' => ($rec['position'] ?? '') !== '' ? $rec['position'] : null,
                'phone' => $phone,
                'email' => $email,
                'whatsapp' => $whatsapp,
                'status' => 'approved',
                'added_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            $ok++;
        }
        fclose($h);

        $this->audit->log($user, 'authority_contact.import', 'authority_contact', 'bulk', [
            'imported' => $ok,
            'skipped' => $skip,
        ]);

        return back()->with('status', __('authcontact.import_done', ['ok' => $ok, 'skip' => $skip]))
            ->with('import_errors', $errors);
    }
}