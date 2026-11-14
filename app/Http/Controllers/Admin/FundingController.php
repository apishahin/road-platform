<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundCampaign;
use App\Models\FundDonation;
use App\Models\FundHead;
use App\Services\AuditService;
use App\Support\Funding;
use App\Support\PaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundingController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->guard();
        $campaigns = FundCampaign::orderByDesc('id')->get();
        $isNew = $request->query('c') === 'new';
        $sel = $campaigns->firstWhere('id', (int) $request->query('c'))
            ?? $campaigns->firstWhere('is_active', true)
            ?? $campaigns->first();
        $edit = $isNew ? null : $sel;

        return view('admin.funding', [
            'campaigns' => $campaigns,
            'edit'      => $edit,
            'raised'    => $edit ? Funding::raised($edit) : 0,
            'donations' => $edit ? FundDonation::where('campaign_id', $edit->id)->latest('id')->limit(300)->get() : collect(),
            'expenses'  => $edit ? \App\Models\FundExpense::where('campaign_id', $edit->id)->orderByDesc('spent_on')->orderByDesc('id')->get() : collect(),
        ]);
    }

    public function save(Request $request, AuditService $audit)
    {
        $this->guard();
        $data = $request->validate([
            'id'             => ['nullable', 'integer', 'exists:fund_campaigns,id'],
            'title'          => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'goal_amount'    => ['required', 'integer', 'min:1', 'max:1000000000'],
            'heads'          => ['nullable', 'array', 'max:30'],
            'heads.*.name'   => ['nullable', 'string', 'max:150'],
            'heads.*.amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'heads.*.id'     => ['nullable', 'integer'],
        ]);

        $c = ! empty($data['id']) ? FundCampaign::findOrFail($data['id']) : new FundCampaign();

        DB::transaction(function () use ($c, $data, $request) {
            $c->fill([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'goal_amount' => (int) $data['goal_amount'],
                'is_active'   => $request->boolean('is_active'),
                'show_donors' => $request->boolean('show_donors'),
            ])->save();

            $keep = [];
            $sort = 0;
            foreach (($data['heads'] ?? []) as $h) {
                $name = trim((string) ($h['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $row = ['name' => $name, 'amount' => (int) ($h['amount'] ?? 0), 'sort' => $sort++];
                $hid = (int) ($h['id'] ?? 0);
                $head = $hid ? FundHead::where('campaign_id', $c->id)->where('id', $hid)->first() : null;
                if ($head) {
                    $head->update($row);
                } else {
                    $head = FundHead::create($row + ['campaign_id' => $c->id]);
                }
                $keep[] = $head->id;
            }
            FundHead::where('campaign_id', $c->id)->whereNotIn('id', $keep)->delete();

            if ($c->is_active) {
                FundCampaign::where('id', '!=', $c->id)->update(['is_active' => false]);
            }
        });

        $audit->log($request->user(), 'fund.campaign_saved', 'fund_campaign', (string) $c->id, [
            'goal' => $c->goal_amount, 'active' => $c->is_active, 'show_donors' => $c->show_donors,
        ]);

        return redirect()->route('admin.funding.index', ['c' => $c->id])->with('status', __('issues.fund_saved'));
    }

    public function manual(Request $request, AuditService $audit)
    {
        $this->guard();
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:fund_campaigns,id'],
            'amount'      => ['required', 'integer', 'min:10', 'max:1000000000'],
            'donor_name'  => ['nullable', 'string', 'max:120'],
            'note'        => ['nullable', 'string', 'max:1000'],
            'paid_at'     => ['nullable', 'date'],
        ]);

        $name = trim((string) ($data['donor_name'] ?? ''));
        $d = FundDonation::create([
            'campaign_id' => $data['campaign_id'],
            'amount'      => (int) $data['amount'],
            'status'      => 'paid',
            'gateway'     => 'manual',
            'is_manual'   => true,
            'donor_name'  => $name !== '' ? $name : null,
            'show_name'   => $name !== '' && $request->boolean('show_name'),
            'hide_amount' => $request->boolean('hide_amount'),
            'note'        => $data['note'] ?? null,
            'recorded_by' => $request->user()->id,
            'paid_at'     => $data['paid_at'] ?? now(),
        ]);

        $audit->log($request->user(), 'fund.manual_added', 'fund_donation', (string) $d->id, ['amount' => $d->amount]);

        return redirect()->route('admin.funding.index', ['c' => $d->campaign_id])->with('status', __('issues.fund_saved'));
    }

    public function void(Request $request, FundDonation $donation, AuditService $audit)
    {
        $this->guard();
        abort_unless($donation->is_manual && $donation->status === 'paid', 422);
        $donation->forceFill(['status' => 'void'])->save();

        $audit->log($request->user(), 'fund.manual_voided', 'fund_donation', (string) $donation->id, ['amount' => $donation->amount]);

        return redirect()->route('admin.funding.index', ['c' => $donation->campaign_id])->with('status', __('issues.fund_voided'));
    }

    public function saveGateway(Request $request, AuditService $audit)
    {
        $this->guard();
        $data = $request->validate([
            'up_base_url'   => ['nullable', 'string', 'max:200', 'regex:~^https?://~i'],
            'up_api_key'    => ['nullable', 'string', 'max:200'],
            'up_min_amount' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        PaySettings::save([
            'up_enabled'    => $request->boolean('up_enabled'),
            'up_base_url'   => $data['up_base_url'] ?? '',
            'up_api_key'    => $data['up_api_key'] ?? '',
            'up_min_amount' => $data['up_min_amount'] ?? 10,
        ]);

        $audit->log($request->user(), 'fund.gateway_saved', 'settings', 'pay_settings', [
            'enabled' => PaySettings::enabled(),
            'base'    => PaySettings::baseUrl(),
        ]);

        return redirect()->route('admin.funding.index')->with('status', __('issues.fund_saved'));
    }
}
