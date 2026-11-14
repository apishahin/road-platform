<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('fund_campaigns', function (Blueprint $table) {
            $table->date('ends_at')->nullable()->after('goal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('fund_campaigns', function (Blueprint $table) {
            $table->dropColumn('ends_at');
        });
    }
};
