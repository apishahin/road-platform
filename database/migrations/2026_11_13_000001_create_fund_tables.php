<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->unsignedBigInteger('goal_amount');
            $t->boolean('is_active')->default(false);
            $t->boolean('show_donors')->default(false);
            $t->timestamps();
        });

        Schema::create('fund_heads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('fund_campaigns')->cascadeOnDelete();
            $t->string('name', 150);
            $t->unsignedBigInteger('amount')->default(0);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('fund_donations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('fund_campaigns')->cascadeOnDelete();
            $t->unsignedBigInteger('amount');
            $t->string('currency', 3)->default('BDT');
            $t->string('status', 20)->default('pending')->index(); // pending|paid|failed|refunded|void
            $t->string('gateway', 30)->default('manual');
            $t->string('tran_id', 100)->nullable()->unique();
            $t->string('donor_name', 120)->nullable();
            $t->string('donor_contact', 150)->nullable();
            $t->boolean('show_name')->default(false);
            $t->boolean('hide_amount')->default(false);
            $t->boolean('is_manual')->default(false);
            $t->text('note')->nullable();
            $t->unsignedBigInteger('recorded_by')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_donations');
        Schema::dropIfExists('fund_heads');
        Schema::dropIfExists('fund_campaigns');
    }
};
