<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('fund_campaigns')->cascadeOnDelete();
            $t->foreignId('head_id')->nullable()->constrained('fund_heads')->nullOnDelete();
            $t->unsignedBigInteger('amount');
            $t->date('spent_on');
            $t->string('description', 500);
            $t->string('voucher_path', 300)->nullable();
            $t->string('voucher_disk', 30)->nullable();
            $t->string('voucher_mime', 100)->nullable();
            $t->unsignedBigInteger('recorded_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_expenses');
    }
};
