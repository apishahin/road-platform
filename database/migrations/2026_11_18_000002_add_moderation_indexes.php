<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance audit finding: every public issue page filters
 * reports/evidence by moderation status, and the moderation queues filter
 * by 'pending' — with no index these become full scans as data grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->index(['moderation', 'created_at'], 'reports_moderation_idx');
        });
        Schema::table('evidence', function (Blueprint $table) {
            $table->index(['moderation'], 'evidence_moderation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_moderation_idx');
        });
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropIndex('evidence_moderation_idx');
        });
    }
};
