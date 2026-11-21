<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Services\AuditService;
use App\Support\StorageSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorageController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function index()
    {
        $this->guard();

        return view('admin.storage', [
            'choice'  => StorageSettings::choice(),
            'keepOrig' => StorageSettings::keepOriginals(),
            'encode'   => \App\Support\PhotoEncodeSettings::all(),
            'r2'      => StorageSettings::r2ForForm(),
            'r2Ready' => StorageSettings::r2Configured(),
            'private' => StorageSettings::privateDisk(),
            'public'  => StorageSettings::publicDisk(),
            'counts'  => Evidence::query()
                ->selectRaw('private_disk, count(*) as c')
                ->groupBy('private_disk')
                ->pluck('c', 'private_disk'),
        ]);
    }

    public function check(Request $request)
    {
        $this->guard();
        $data = $request->validate(['target' => ['required', 'in:local,r2']]);

        $results = [];
        foreach (StorageSettings::OPTIONS[$data['target']] as $kind => $disk) {
            $results[] = ['label' => $disk, 'result' => StorageSettings::check($disk)];
        }

        return redirect()->route('admin.storage.index')->with('checks', $results);
    }

    /**
     * Admin-only R2 credentials and bucket details. Sensitive values are
     * encrypted by StorageSettings and are intentionally never returned to UI.
     */
    public function saveR2(Request $request, AuditService $audit)
    {
        $this->guard();

        $validator = Validator::make($request->all(), [
            'r2_endpoint' => ['required', 'url', 'starts_with:https://', 'max:255'],
            'r2_private_bucket' => ['required', 'regex:/^[a-z0-9][a-z0-9.-]{1,61}$/'],
            'r2_public_bucket' => ['required', 'regex:/^[a-z0-9][a-z0-9.-]{1,61}$/'],
            'r2_public_url' => ['required', 'url', 'starts_with:https://', 'max:255'],
            'r2_access_key_id' => ['nullable', 'string', 'max:300'],
            'r2_secret_access_key' => ['nullable', 'string', 'max:300'],
            'clear_r2_access_key_id' => ['nullable', 'boolean'],
            'clear_r2_secret_access_key' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return back()
                ->withInput($request->except(['r2_access_key_id', 'r2_secret_access_key']))
                ->withErrors($validator);
        }
        $data = $validator->validated();

        $current = StorageSettings::r2ForForm();
        $clearKey = $request->boolean('clear_r2_access_key_id');
        $clearSecret = $request->boolean('clear_r2_secret_access_key');
        $accessKeyId = trim((string) ($data['r2_access_key_id'] ?? ''));
        $secretAccessKey = trim((string) ($data['r2_secret_access_key'] ?? ''));

        if ($accessKeyId === '' && ! $current['has_access_key_id'] && ! $clearKey) {
            return back()->withInput($request->except(['r2_access_key_id', 'r2_secret_access_key']))
                ->withErrors(['r2_access_key_id' => 'R2 Access Key ID দিন।']);
        }
        if ($secretAccessKey === '' && ! $current['has_secret_access_key'] && ! $clearSecret) {
            return back()->withInput($request->except(['r2_access_key_id', 'r2_secret_access_key']))
                ->withErrors(['r2_secret_access_key' => 'R2 Secret Access Key দিন।']);
        }

        StorageSettings::saveR2([
            'endpoint' => $data['r2_endpoint'],
            'private_bucket' => $data['r2_private_bucket'],
            'public_bucket' => $data['r2_public_bucket'],
            'public_url' => $data['r2_public_url'],
        ], $accessKeyId, $secretAccessKey, $clearKey, $clearSecret);

        $audit->log($request->user(), 'storage.r2_saved', 'setting', 'r2_storage', [
            'endpoint' => rtrim($data['r2_endpoint'], '/'),
            'private_bucket' => $data['r2_private_bucket'],
            'public_bucket' => $data['r2_public_bucket'],
            'public_url' => rtrim($data['r2_public_url'], '/'),
            'access_key_changed' => $accessKeyId !== '' || $clearKey,
            'secret_key_changed' => $secretAccessKey !== '' || $clearSecret,
        ]);

        return redirect()->route('admin.storage.index')
            ->with('status', 'R2-এর সেটিং এনক্রিপ্ট করে সংরক্ষণ হয়েছে। এখন “পরীক্ষা করুন” চাপুন।');
    }

    /** Admin only: keep or discard the untouched original upload. */
    public function keepOriginals(Request $request, AuditService $audit)
    {
        $this->guard();

        $data = $request->validate(['keep' => ['required', 'in:0,1']]);
        $on = $data['keep'] === '1';
        StorageSettings::setKeepOriginals($on);
        $audit->log($request->user(), 'storage.keep_originals', 'setting', 'keep_originals', ['keep' => $on]);

        return back()->with('status', $on ? 'অরিজিনাল ছবি এখন থেকে সংরক্ষিত হবে।' : 'অরিজিনাল ছবি আর রাখা হবে না — শুধু ছোট কপি থাকবে।');
    }

    /** Admin only: processed-photo size / format / quality. */
    public function saveEncode(Request $request, AuditService $audit)
    {
        $this->guard();

        $data = $request->validate([
            'max'     => ['required', 'integer', 'in:' . implode(',', \App\Support\PhotoEncodeSettings::SIZES)],
            'format'  => ['required', 'in:jpeg,webp'],
            'quality' => ['required', 'integer', 'min:50', 'max:92'],
        ]);

        \App\Support\PhotoEncodeSettings::save((int) $data['max'], $data['format'], (int) $data['quality']);
        $audit->log($request->user(), 'storage.encode', 'setting', 'photo_encode', $data);

        return back()->with('status', 'ফটো সেটিং সংরক্ষণ হয়েছে — এখন থেকে নতুন আপলোডে কার্যকর হবে।');
    }

    public function update(Request $request, AuditService $audit)
    {
        $this->guard();
        $data = $request->validate(['storage' => ['required', 'in:local,r2']]);
        $target = $data['storage'];
        $disks = StorageSettings::OPTIONS[$target];

        if ($target === 'r2' && ! StorageSettings::r2Configured()) {
            return back()->withErrors(['storage' => 'R2-এর তথ্য .env ফাইলে সম্পূর্ণ সেট করা নেই।']);
        }

        foreach ($disks as $disk) {
            $r = StorageSettings::check($disk);
            if (! $r['ok']) {
                return back()->withErrors(['storage' => "ডিস্ক '$disk' কাজ করছে না: " . $r['message']]);
            }
        }

        $blocked = Evidence::query()
            ->where('moderation', '!=', 'approved')
            ->where(function ($q) use ($disks) {
                $q->whereNull('private_disk')->orWhere('private_disk', '!=', $disks['private']);
            })
            ->count();

        if ($blocked > 0) {
            return back()->withErrors([
                'storage' => "$blocked টি অনুমোদনহীন ছবি পুরনো ডিস্কে আছে। সেগুলোর কাজ শেষ হওয়ার পর স্টোরেজ বদলান।",
            ]);
        }

        $from = StorageSettings::choice();
        StorageSettings::set($target);
        $audit->log($request->user(), 'storage.changed', 'setting', 'evidence_storage', ['from' => $from, 'to' => $target]);

        return redirect()->route('admin.storage.index')->with('status', 'স্টোরেজ বদলানো হয়েছে। এখন থেকে নতুন ছবি এখানে জমা হবে।');
    }
}