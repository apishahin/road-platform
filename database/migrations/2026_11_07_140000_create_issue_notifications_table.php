<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->restrictOnDelete();
            $table->foreignId('authority_contact_id')->constrained('authority_contacts')->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->text('message')->nullable();
            $table->string('error', 200)->nullable();
            $table->timestamps();
            $table->index(['issue_id', 'created_at']);
            $table->index(['authority_contact_id', 'channel', 'created_at'], 'issue_notif_contact_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_notifications');
    }
};