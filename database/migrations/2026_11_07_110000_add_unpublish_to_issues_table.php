<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->timestamp('unpublished_at')->nullable();
            $table->unsignedBigInteger('unpublished_by')->nullable();
            $table->string('unpublish_reason', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['unpublished_at', 'unpublished_by', 'unpublish_reason']);
        });
    }
};