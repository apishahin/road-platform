<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_area_id')->constrained('admin_areas');
            $table->foreignId('authority_id')->nullable()->constrained('authorities');
            $table->string('name', 150);
            $table->string('position', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->enum('status', ['pending', 'approved', 'disabled'])->default('pending');
            $table->foreignId('added_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['admin_area_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_contacts');
    }
};