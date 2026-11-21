<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The work center reads pending evidence by both possible issue links.
        Schema::table('evidence', function (Blueprint $table) {
            $table->index(['moderation', 'observation_id'], 'evidence_review_observation_idx');
            $table->index(['moderation', 'status_event_id'], 'evidence_review_status_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropIndex('evidence_review_observation_idx');
            $table->dropIndex('evidence_review_status_event_idx');
        });
    }
};
