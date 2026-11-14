<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundExpense;
use App\Models\FundHead;
use App\Services\AuditService;
use App\Support\StorageSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FundExpenseController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->guard();
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:fund_campaigns,id'],
            'head_id'     => ['nullable', 'integer'],
            'amount'      => ['required', 'integer', 'min:1', 'max:1000000000'],
            'spent_on'    => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'voucher'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $headId = ! empty($data['head_id']) ? (int) $data['head_id'] : null;
        if ($headId && ! FundHead::where('id', $headId)->where('campaign_id', $data['campaign_id'])->exists()) {
            return back()->withErrors(['head_id' => __('issues.fund_bad_head')])->withInput();
        }

        $path = null;
        $disk = null;
        $mime = null;
        if ($request->hasFile('voucher')) {
            $f = $request->file('voucher');
            $disk = StorageSettings::publicDisk();
            $name = (string) Str::ulid() . '.' . strtolower((string) $f->extension());
            $path = Storage::disk($disk)->putFileAs('funding/vouchers', $f, $name);
            if (! $path) {
                return back()->withErrors(['voucher' => __('issues.fund_upload_failed')])->withInput();
            }
            $mime = $f->getMimeType();
        }

        $e = FundExpense::create([
            'campaign_id'  => $data['campaign_id'],
            'head_id'      => $headId,
            'amount'       => (int) $data['amount'],
            'spent_on'     => $data['spent_on'],
            'description'  => trim($data['description']),
            'voucher_path' => $path,
            'voucher_disk' => $disk,
            'voucher_mime' => $mime,
            'recorded_by'  => $request->user()->id,
        ]);

        $audit->log($request->user(), 'fund.expense_added', 'fund_expense', (string) $e->id, [
            'amount' => $e->amount, 'voucher' => $path !== null,
        ]);

        return redirect()->route('admin.funding.index', ['c' => $e->campaign_id])->with('status', __('issues.fund_saved'));
    }

    public function destroy(Request $request, FundExpense $expense, AuditService $audit)
    {
        $this->guard();
        $campaignId = $expense->campaign_id;
        $info = ['amount' => $expense->amount, 'description' => Str::limit($expense->description, 100)];

        if ($expense->voucher_path && $expense->voucher_disk) {
            Storage::disk($expense->voucher_disk)->delete($expense->voucher_path);
        }
        $id = $expense->id;
        $expense->delete();

        $audit->log($request->user(), 'fund.expense_deleted', 'fund_expense', (string) $id, $info);

        return redirect()->route('admin.funding.index', ['c' => $campaignId])->with('status', __('issues.fund_expense_deleted'));
    }
}
