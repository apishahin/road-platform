<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Evidence extends Model
{
    protected $fillable = [
        'report_id', 'status_event_id', 'uploader_id',
        'public_path', 'private_path', 'taken_at', 'moderation',
    ];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function statusEvent(): BelongsTo
    {
        return $this->belongsTo(StatusEvent::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /** Public URL of the processed photo. Only show it when moderation is "approved". */
    public function url(): string
    {
        return Storage::disk(config('filesystems.evidence.public'))->url($this->public_path);
    }
}