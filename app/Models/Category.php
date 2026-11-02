<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['slug', 'name_bn', 'name_en', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /** Category name in the current language (Bangla or English). */
    public function name(): string
    {
        return app()->getLocale() === 'bn'
            ? $this->name_bn
            : ($this->name_en ?: $this->name_bn);
    }
}