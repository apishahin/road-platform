<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Authority extends Model
{
    protected $fillable = [
        'name_bn', 'name_en', 'type', 'admin_area_id',
        'public_phone', 'public_email', 'website', 'source_url',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return ['last_verified_at' => 'date'];
    }

    public function adminArea(): BelongsTo
    {
        return $this->belongsTo(AdminArea::class);
    }

    public function issueLinks(): HasMany
    {
        return $this->hasMany(IssueAuthorityLink::class);
    }
}