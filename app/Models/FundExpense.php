<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FundExpense extends Model
{
    protected $table = 'fund_expenses';

    protected $fillable = [
        'campaign_id', 'head_id', 'amount', 'spent_on', 'description',
        'voucher_path', 'voucher_disk', 'voucher_mime', 'recorded_by',
    ];

    protected $casts = ['amount' => 'integer', 'spent_on' => 'date'];

    public function voucherUrl(): ?string
    {
        if (! $this->voucher_path || ! $this->voucher_disk) {
            return null;
        }

        return Storage::disk($this->voucher_disk)->url($this->voucher_path);
    }
}
