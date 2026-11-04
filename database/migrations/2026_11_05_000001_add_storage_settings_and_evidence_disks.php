<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key', 100)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('evidence', function (Blueprint $table) {
            $table->string('private_disk', 50)->nullable();
            $table->string('public_disk', 50)->nullable();
        });

        // Existing photos live on the currently configured disks.
        DB::table('evidence')->update([
            'private_disk' => config('filesystems.evidence.private'),
        ]);
        DB::table('evidence')->where('moderation', 'approved')->update([
            'public_disk' => config('filesystems.evidence.public'),
        ]);
    }

    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropColumn(['private_disk', 'public_disk']);
        });
        Schema::dropIfExists('settings');
    }
};