<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('issue_follows')) {
            Schema::create('issue_follows', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('issue_id')->index();
                $t->unsignedBigInteger('user_id')->index();
                $t->timestamp('created_at')->nullable();
                $t->unique(['issue_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->unsignedBigInteger('issue_id')->nullable()->index();
                $t->unsignedBigInteger('status_event_id')->nullable();
                $t->string('title', 190);
                $t->string('url', 300)->nullable();
                $t->timestamp('read_at')->nullable();
                $t->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('issue_follows');
    }
};
