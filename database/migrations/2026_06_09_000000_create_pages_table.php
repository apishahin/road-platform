<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('title_bn', 150);
            $table->string('title_en', 150)->nullable();
            $table->longText('body_bn');
            $table->longText('body_en')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('in_menu')->default(false);
            $table->smallInteger('menu_order')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};