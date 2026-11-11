<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_gateways', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('channel', 10)->default('sms');
            $t->boolean('enabled')->default(false);
            $t->unsignedInteger('priority')->default(10);
            $t->string('method', 4)->default('GET');
            $t->string('url', 500);
            $t->string('body_type', 10)->default('query');
            $t->text('params')->nullable();
            $t->text('headers')->nullable();
            $t->string('sender_id', 60)->nullable();
            $t->string('success_marker', 100)->nullable();
            $t->text('api_key_enc')->nullable();
            $t->string('last_status', 10)->nullable();
            $t->string('last_note', 255)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_gateways');
    }
};