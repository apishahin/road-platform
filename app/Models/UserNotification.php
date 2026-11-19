<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function statusEvent(): BelongsTo
    {
        return $this->belongsTo(StatusEvent::class, 'status_event_id');
    }

    public static function unreadCount(int $userId): int
    {
        return static::where('user_id', $userId)->whereNull('read_at')->count();
    }
}
