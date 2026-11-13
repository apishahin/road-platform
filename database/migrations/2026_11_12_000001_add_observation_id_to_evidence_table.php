<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->unsignedBigInteger('observation_id')->nullable()->after('status_event_id');
            $table->index('observation_id');
        });
    }

    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropIndex(['observation_id']);
            $table->dropColumn('observation_id');
        });
    }
};