<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundDonation extends Model
{
    protected $table = 'fund_donations';

    protected $fillable = [
        'campaign_id', 'amount', 'currency', 'status', 'gateway', 'tran_id', 'invoice_id',
        'donor_name', 'donor_contact', 'show_name', 'hide_amount', 'is_manual',
        'note', 'recorded_by', 'paid_at',
    ];

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FundCampaign::class, 'campaign_id');
    }

    protected $casts = [
        'amount'      => 'integer',
        'show_name'   => 'boolean',
        'hide_amount' => 'boolean',
        'is_manual'   => 'boolean',
        'paid_at'     => 'datetime',
    ];
}
