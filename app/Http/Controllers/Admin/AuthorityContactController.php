<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminArea;
use App\Models\AuthorityContact;
use App\Services\AuditService;
use App\Support\ModerationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthorityContactController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isModerator(), 403);
    }

    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    /** Admins may manage every entry; moderators may manage only entries they added. */
    private function manage(Request $request, AuthorityContact $contact): void
    {
        $this->guard($request);
        abort_unless(
            $request->user()->role === 'admin' || (int) $contact->added_by === (int) $request->user()->id,
            403
        );
    }

    private function clean(?string $value): ?string
    {
        $value = preg_replace('/[^0-9+]/', '', (string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array{admin_area_id:int,name:string,position:?string,phone:?string,email:?string,whatsapp:?string} */
    private function contactData(Request $request): array
    {
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
        $email = ! empty($data['email']) ? trim((string) $data['email']) : null;
        if (! $phone && ! $whatsapp && ! $email) {
            throw ValidationException::withMessages(['phone' => __('authcontact.need_one')]);
        }

        return [
            'admin_area_id' => (int) $data['admin_area_id'],
            'name' => trim($data['name']),
            'position' => ! empty($data['position']) ? trim($data['position']) : null,
            'phone' => $phone,
            'email' => $email,
            'whatsapp' => $whatsapp,
        ];
    }

    /** The selected area's complete Division → District → Upazila → Union chain. */
    private function areaFormData(?AuthorityContact $contact = null): array
    {
        $oldArea = old('admin_area_id');
        $areaId = $oldArea !== null ? (int) $oldArea : (int) ($contact?->admin_area_id ?? 0);
        $area = $areaId ? AdminArea::find($areaId) : null;
        $selection = [];
        $node = $area;
        while ($node) {
            $selection[$node->level] = $node->id;
            $node = $node->parent;
        }

        $branchIds = ModerationScope::branchIds(auth()->user());
        $divisions = AdminArea::where('level', 'division')->orderBy('name_en')->orderBy('name_bn');
        if ($branchIds !== null) {
            $divisions->whereIn('id', $branchIds);
        }

        return [
            'divisions' => $divisions->get(),
            'areaSelection' => $selection,
            'areaLabel' => $area ? AuthorityContact::pathOf($area) : '',
        ];
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $status = in_array($request->query('status'), ['pending', 'approved', 'disabled'], true)
            ? $request->query('status') : null;

        $query = AuthorityContact::with(['area.parent.parent.parent', 'addedBy'])->orderByDesc('id');
        if (! $isAdmin) {
            $query->where('added_by', $user->id);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return view('admin.contacts.index', array_merge([
            'contacts' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'isAdmin' => $isAdmin,
            'pendingCount' => $isAdmin ? AuthorityContact::where('status', 'pending')->count() : 0,
        ], $this->areaFormData()));
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $user = $request->user();
        $data = $this->contactData($request);
        ModerationScope::authorizeArea($user, $data['admin_area_id']);

        $isAdmin = $user->role === 'admin';
        $contact = AuthorityContact::create($data + [
            'status' => $isAdmin ? 'approved' : 'pending',
            'added_by' => $user->id,
            'approved_by' => $isAdmin ? $user->id : null,
            'approved_at' => $isAdmin ? now() : null,
        ]);
        $this->audit->log($user, 'authority_contact.add', 'authority_contact', (string) $contact->id, [
            'area_id' => $contact->admin_area_id,
            'status' => $contact->status,
        ]);

        return redirect()->route('admin.contacts.index')
            ->with('status', __($isAdmin ? 'authcontact.added_ok' : 'authcontact.added_pending'));
    }

    public function edit(Request $request, AuthorityContact $contact)
    {
        $this->manage($request, $contact);

        return view('admin.contacts.edit', array_merge([
            'contact' => $contact,
        ], $this->areaFormData($contact)));
    }

    public function update(Request $request, AuthorityContact $contact)
    {
        $this->manage($request, $contact);
        $user = $request->user();
        $data = $this->contactData($request);
        ModerationScope::authorizeArea($user, $data['admin_area_id']);

        $before = ['area_id' => $contact->admin_area_id, 'status' => $contact->status];
        if ($user->role !== 'admin' && $contact->status === 'approved') {
            // A moderator's material change is reviewed again before it can receive notifications.
            $data['status'] = 'pending';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
        }
        $contact->forceFill($data)->save();

        $this->audit->log($user, 'authority_contact.update', 'authority_contact', (string) $contact->id, [
            'before' => $before,
            'area_id' => $contact->admin_area_id,
            'status' => $contact->status,
        ]);

        return redirect()->route('admin.contacts.index')->with('status', __('authcontact.updated_msg'));
    }

    /** Soft deletion preserves linked notification history and prevents foreign-key failures. */
    public function destroy(Request $request, AuthorityContact $contact)
    {
        $this->manage($request, $contact);
        $user = $request->user();
        $details = ['area_id' => $contact->admin_area_id, 'status' => $contact->status];
        $contact->delete();
        $this->audit->log($user, 'authority_contact.delete', 'authority_contact', (string) $contact->id, $details);

        return redirect()->route('admin.contacts.index')->with('status', __('authcontact.deleted_msg'));
    }

    private function setStatus(Request $request, AuthorityContact $contact, string $to, string $action, string $message)
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

        return back()->with('status', __($message));
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

    /** Kept for integrations using the original text-search picker. */
    public function areaSearch(Request $request)
    {
        $this->guard($request);
        $query = trim((string) $request->query('q'));
        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $rows = AdminArea::with('parent.parent.parent')
            ->where(function ($where) use ($like) {
                $where->where('name_bn', 'like', $like)->orWhere('name_en', 'like', $like);
            })
            ->orderBy('level')->orderBy('name_bn')->limit(20)->get();

        return response()->json($rows->map(fn ($area) => [
            'id' => $area->id,
            'label' => AuthorityContact::pathOf($area),
        ])->values());
    }

    public function template(Request $request)
    {
        $this->adminOnly($request);
        $id = AdminArea::where('level', 'division')->value('id') ?? 1;

        return response()->streamDownload(function () use ($id) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['area_id', 'name', 'position', 'phone', 'email', 'whatsapp'], ',', '"', '');
            fputcsv($output, [$id, '[Demo] Divisional Commissioner', 'Commissioner', '01700000001', 'demo1@example.com', '01700000001'], ',', '"', '');
            fputcsv($output, [$id, '[Demo] Deputy Commissioner', 'DC', '01700000002', 'demo2@example.com', ''], ',', '"', '');
            fclose($output);
        }, 'authority-contacts-demo.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function areasCsv(Request $request)
    {
        $this->adminOnly($request);

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['area_id', 'level', 'area_name'], ',', '"', '');
            AdminArea::with('parent.parent.parent')->chunkById(500, function ($rows) use ($output) {
                foreach ($rows as $area) {
                    fputcsv($output, [$area->id, $area->level, AuthorityContact::pathOf($area)], ',', '"', '');
                }
            });
            fclose($output);
        }, 'area-ids.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $this->adminOnly($request);
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimes:csv,txt']]);
        $user = $request->user();

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = null;
        $row = 0;
        $ok = 0;
        $skip = 0;
        $errors = [];

        while (($cols = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row++;
            if ($row === 1) {
                $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cols[0] ?? ''));
                $header = array_map(fn ($column) => strtolower(trim((string) $column)), $cols);
                if (! in_array('area_id', $header, true) || ! in_array('name', $header, true)) {
                    fclose($handle);

                    return back()->with('import_errors', [__('authcontact.bad_header')]);
                }
                continue;
            }
            if ($row > 1001) {
                $errors[] = __('authcontact.too_many');
                break;
            }
            if (! array_filter($cols, fn ($column) => trim((string) $column) !== '')) {
                continue;
            }
            if (! mb_check_encoding(implode('', array_map('strval', $cols)), 'UTF-8')) {
                $skip++;
                if (count($errors) < 10) {
                    $errors[] = "#$row: " . __('authcontact.bad_encoding');
                }
                continue;
            }

            $record = [];
            foreach ($header as $i => $key) {
                $record[$key] = trim((string) ($cols[$i] ?? ''));
            }
            $phone = $this->clean($record['phone'] ?? null);
            $whatsapp = $this->clean($record['whatsapp'] ?? null);
            $email = ($record['email'] ?? '') !== '' ? $record['email'] : null;

            $validator = Validator::make([
                'area_id' => $record['area_id'] ?? '',
                'name' => $record['name'] ?? '',
                'position' => $record['position'] ?? '',
                'email' => $email,
            ], [
                'area_id' => ['required', 'integer', 'exists:admin_areas,id'],
                'name' => ['required', 'max:150'],
                'position' => ['nullable', 'max:150'],
                'email' => ['nullable', 'email', 'max:150'],
            ]);
            if ($validator->fails() || (! $phone && ! $whatsapp && ! $email)) {
                $skip++;
                if (count($errors) < 10) {
                    $errors[] = "#$row: " . ($validator->fails() ? $validator->errors()->first() : __('authcontact.need_one'));
                }
                continue;
            }

            $areaId = (int) $record['area_id'];
            $duplicate = AuthorityContact::where('admin_area_id', $areaId)
                ->where(function ($query) use ($phone, $email, $whatsapp) {
                    if ($phone) {
                        $query->orWhere('phone', $phone);
                    }
                    if ($email) {
                        $query->orWhere('email', $email);
                    }
                    if ($whatsapp) {
                        $query->orWhere('whatsapp', $whatsapp);
                    }
                })->exists();
            if ($duplicate) {
                $skip++;
                continue;
            }

            AuthorityContact::create([
                'admin_area_id' => $areaId,
                'name' => $record['name'],
                'position' => ($record['position'] ?? '') !== '' ? $record['position'] : null,
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
        fclose($handle);

        $this->audit->log($user, 'authority_contact.import', 'authority_contact', 'bulk', [
            'imported' => $ok,
            'skipped' => $skip,
        ]);

        return back()->with('status', __('authcontact.import_done', ['ok' => $ok, 'skip' => $skip]))
            ->with('import_errors', $errors);
    }
}
