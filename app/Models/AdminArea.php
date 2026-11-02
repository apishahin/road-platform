<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminArea extends Model
{
    protected $fillable = ['parent_id', 'level', 'name_bn', 'name_en'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AdminArea::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AdminArea::class, 'parent_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}