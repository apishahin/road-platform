<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModeratorArea extends Model
{
    protected $fillable = ['user_id', 'admin_area_id', 'granted_by', 'revoked_at', 'revoked_by'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(AdminArea::class, 'admin_area_id');
    }
}