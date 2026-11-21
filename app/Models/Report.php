<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggested_area_id',
        'issue_id', 'user_id', 'description', 'lat', 'lng', 'moderation',
    ];

    protected static function booted(): void
    {
        static::creating(function (Report $report) {
            if (empty($report->public_id)) {
                $report->public_id = (string) Str::ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function suggestedArea(): BelongsTo
    {
        return $this->belongsTo(AdminArea::class, 'suggested_area_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}