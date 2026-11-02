<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * APPEND-ONLY. Never update or delete a status event.
 * Every status change in the system goes through IssueStatusService.
 */
class StatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    // ── Block edits and deletes ────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException(
                'Status events are append-only and cannot be modified.'
            );
        });

        static::deleting(function () {
            throw new LogicException(
                'Status events cannot be deleted.'
            );
        });
    }

    // ── Relationships ──────────────────────────────────

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}