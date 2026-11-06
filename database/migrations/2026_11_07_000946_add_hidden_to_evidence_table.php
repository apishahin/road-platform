<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedBigInteger('hidden_by')->nullable();
            $table->string('hidden_reason', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropColumn(['hidden_at', 'hidden_by', 'hidden_reason']);
        });
    }
};