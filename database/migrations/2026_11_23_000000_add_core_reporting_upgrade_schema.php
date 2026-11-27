<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original category rows remain the reportable leaves.  A non-reportable
     * parent is added above them so existing issues keep their category IDs while
     * new civic domains can be added without changing the issues table again.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
            $table->boolean('is_reportable')->default(true)->after('is_active');
            $table->index(['parent_id', 'is_active', 'is_reportable']);
        });

        $rootId = DB::table('categories')->where('slug', 'road-and-mobility')->value('id');
        if (! $rootId) {
            $rootId = DB::table('categories')->insertGetId([
                'slug' => 'road-and-mobility',
                'name_bn' => 'সড়ক ও নিরাপদ চলাচল',
                'name_en' => 'Roads and safe mobility',
                'sort' => 0,
                'is_active' => true,
                'is_reportable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Baseline installations used a flat road taxonomy.  Preserve all
        // existing category IDs and simply place every legacy top-level row
        // beneath the new parent. New top-level categories are left untouched.
        DB::table('categories')
            ->where('id', '!=', $rootId)
            ->whereNull('parent_id')
            ->update(['parent_id' => $rootId, 'updated_at' => now()]);

        Schema::table('issues', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->change();
            $table->decimal('lng', 10, 7)->nullable()->change();
            $table->string('location_precision', 20)->default('exact')->after('lng');
            $table->string('location_review_status', 32)->default('ready')->after('location_precision');
            $table->string('place_name', 200)->nullable()->after('landmark');
            $table->string('location_note', 500)->nullable()->after('place_name');
            $table->index(['location_review_status', 'published_at']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->change();
            $table->decimal('lng', 10, 7)->nullable()->change();
            $table->string('location_precision', 20)->default('exact')->after('lng');
            $table->string('place_name', 200)->nullable()->after('location_precision');
            $table->string('location_note', 500)->nullable()->after('place_name');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['location_precision', 'place_name', 'location_note']);
            $table->decimal('lat', 10, 7)->nullable(false)->change();
            $table->decimal('lng', 10, 7)->nullable(false)->change();
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['location_review_status', 'published_at']);
            $table->dropColumn(['location_precision', 'location_review_status', 'place_name', 'location_note']);
            $table->decimal('lat', 10, 7)->nullable(false)->change();
            $table->decimal('lng', 10, 7)->nullable(false)->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'is_active', 'is_reportable']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('is_reportable');
        });
    }
};
