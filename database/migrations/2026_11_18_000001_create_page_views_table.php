<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy-friendly visit counters: no IP, no user agent, no cookies — only
 * the date, the path, the referring site and a daily-rotating anonymous
 * hash so "unique visitors" can be counted without knowing who they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('path', 191);
            $table->string('referrer', 191)->nullable(); // host only, never the full URL
            $table->char('vhash', 16);                   // HMAC(ip|ua|date) — rotates daily
            $table->timestamp('created_at')->useCurrent();
            $table->index(['date', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
