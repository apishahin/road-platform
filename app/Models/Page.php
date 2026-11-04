<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = [
        'slug', 'title_bn', 'title_en', 'body_bn', 'body_en',
        'is_published', 'in_menu', 'menu_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'in_menu' => 'boolean',
        'menu_order' => 'integer',
    ];

    /** মেনুতে দেখানোর পেজ (সর্বোচ্চ ৬টি)। টেবিল না থাকলেও সাইট ভাঙবে না। */
    public static function menuItems()
    {
        try {
            return static::where('is_published', true)
                ->where('in_menu', true)
                ->orderBy('menu_order')
                ->orderBy('id')
                ->limit(6)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function title(): string
    {
        if (app()->getLocale() === 'en' && filled($this->title_en)) {
            return $this->title_en;
        }
        return $this->title_bn;
    }

    public function bodyHtml(): string
    {
        $text = (app()->getLocale() === 'en' && filled($this->body_en))
            ? $this->body_en
            : $this->body_bn;

        return Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}