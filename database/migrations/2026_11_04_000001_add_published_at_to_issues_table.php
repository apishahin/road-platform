<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('last_activity_at')->index();
        });

        // Issues that already exist (demo data) stay public.
        DB::table('issues')->whereNull('published_at')->update(['published_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};