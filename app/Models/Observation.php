<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observation extends Model
{
    protected $fillable = ['issue_id', 'user_id', 'type', 'note'];

    public const TYPES = ['still_exists', 'got_worse', 'partially_fixed', 'fixed'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'still_exists'    => 'এখনো বিদ্যমান',
            'got_worse'       => 'আরও খারাপ হয়েছে',
            'partially_fixed' => 'আংশিক সংশোধন',
            'fixed'           => 'সংশোধিত হয়েছে',
            default           => $this->type,
        };
    }
}