<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authority_contacts', function (Blueprint $table) {
            // Keep historic issue-notification records intact when a contact is removed.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('authority_contacts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
