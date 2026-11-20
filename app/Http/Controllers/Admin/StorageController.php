<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Services\AuditService;
use App\Support\StorageSettings;
use Illuminate\Http\Request;

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