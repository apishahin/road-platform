<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundCampaign extends Model
{
    protected $table = 'fund_campaigns';

    protected $fillable = ['title', 'description', 'goal_amount', 'ends_at', 'is_active', 'show_donors'];

    protected $casts = [
        'goal_amount' => 'integer',
        'ends_at'     => 'date',
        'is_active'   => 'boolean',
        'show_donors' => 'boolean',
    ];

    public function heads(): HasMany
    {
        return $this->hasMany(FundHead::class, 'campaign_id')->orderBy('sort')->orderBy('id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(FundDonation::class, 'campaign_id');
    }
}
