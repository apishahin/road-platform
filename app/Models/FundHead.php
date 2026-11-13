<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundHead extends Model
{
    protected $table = 'fund_heads';

    protected $fillable = ['campaign_id', 'name', 'amount', 'sort'];

    protected $casts = ['amount' => 'integer', 'sort' => 'integer'];
}
