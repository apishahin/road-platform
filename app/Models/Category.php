<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'slug', 'name_bn', 'name_en', 'sort', 'is_active', 'is_reportable',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_reportable' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function scopeReportable($query)
    {
        return $query->where('is_active', true)->where('is_reportable', true);
    }

    /** Category name in the current language (Bangla or English). */
    public function name(): string
    {
        return app()->getLocale() === 'bn'
            ? $this->name_bn
            : ($this->name_en ?: $this->name_bn);
    }
}
