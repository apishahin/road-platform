<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_tokens')) {
            Schema::create('push_tokens', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('token', 500)->unique();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
