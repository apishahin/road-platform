<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorityContact extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(AdminArea::class, 'admin_area_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Division / District / Upazila / Union name path. */
    public static function pathOf(?AdminArea $a): string
    {
        $bn = app()->getLocale() === 'bn';
        $parts = [];
        while ($a) {
            $parts[] = $bn ? $a->name_bn : ($a->name_en ?: $a->name_bn);
            $a = $a->parent;
        }

        return implode(' / ', array_reverse($parts));
    }
}