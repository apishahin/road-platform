<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OTP codes for phone login
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        // Administrative areas: division > district > upazila/city > union/ward
        Schema::create('admin_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('admin_areas');
            $table->enum('level', ['division', 'district', 'upazila', 'city_corporation',
                                   'pourashava', 'union', 'ward'])->index();
            $table->string('name_bn', 150);
            $table->string('name_en', 150)->nullable();
            $table->timestamps();
        });

        // Problem categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name_bn', 100);
            $table->string('name_en', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Issues: one physical road problem
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title', 200);
            $table->foreignId('category_id')->constrained();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('road_name', 200)->nullable();
            $table->string('landmark', 200)->nullable();
            $table->foreignId('admin_area_id')->nullable()->constrained('admin_areas');

            // Three separate status dimensions
            $table->enum('progress_status', ['reported', 'acknowledged', 'planned',
                'work_started', 'completion_reported', 'repaired'])->default('reported');
            $table->enum('verification_status', ['unverified', 'community_confirmed',
                'reviewed', 'verified'])->default('unverified');
            $table->enum('disposition', ['active', 'duplicate', 'rejected',
                'referred', 'reopened'])->default('active');

            $table->foreignId('duplicate_of_id')->nullable()->constrained('issues');
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamp('first_reported_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['lat', 'lng']);
            $table->index(['disposition', 'progress_status']);
        });

        // Reports: one person's submission about an issue
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('issue_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('description');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->enum('moderation', ['pending', 'approved', 'hidden'])
                  ->default('pending')->index();
            $table->timestamps();
        });

        // Status events: APPEND-ONLY history (never update or delete)
        Schema::create('status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained();
            $table->enum('dimension', ['progress', 'verification', 'disposition']);
            $table->string('from_value', 30)->nullable();
            $table->string('to_value', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->enum('actor_type', ['citizen', 'volunteer', 'moderator',
                                        'authority', 'system']);
            $table->enum('source_type', ['platform', 'official_letter', 'news',
                'public_notice', 'site_visit', 'phone_call'])->default('platform');
            $table->string('source_url', 500)->nullable();
            $table->text('reason');
            $table->timestamp('created_at')->nullable();

            $table->index(['issue_id', 'created_at']);
        });

        // Evidence: photos/documents for reports or status events
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->nullable()->constrained();
            $table->foreignId('status_event_id')->nullable()->constrained();
            $table->foreignId('uploader_id')->constrained('users');
            $table->string('public_path', 500);        // resized, EXIF stripped
            $table->string('private_path', 500)->nullable(); // original, never public
            $table->timestamp('taken_at')->nullable();
            $table->enum('moderation', ['pending', 'approved', 'hidden'])->default('pending');
            $table->timestamps();
        });

        // Observations: "still there" / "fixed"
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->enum('type', ['still_exists', 'got_worse', 'partially_fixed', 'fixed']);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'created_at']);
        });

        // Authorities directory
        Schema::create('authorities', function (Blueprint $table) {
            $table->id();
            $table->string('name_bn', 200);
            $table->string('name_en', 200)->nullable();
            $table->enum('type', ['rhd', 'lged', 'city_corporation', 'pourashava',
                'union_parishad', 'wasa', 'utility', 'other']);
            $table->foreignId('admin_area_id')->nullable()->constrained('admin_areas');
            $table->string('public_phone', 50)->nullable();
            $table->string('public_email', 150)->nullable();
            $table->string('website', 300)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->date('last_verified_at')->nullable();
            $table->timestamps();
        });

        // Issue ↔ Authority links, with basis and verification state
        Schema::create('issue_authority_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained();
            $table->foreignId('authority_id')->constrained();
            $table->enum('state', ['proposed', 'verified', 'disputed'])->default('proposed');
            $table->text('basis');
            $table->string('source_url', 500)->nullable();
            $table->foreignId('linked_by')->constrained('users');
            $table->timestamps();
        });

        // Audit log for moderation and admin actions
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action', 50);
            $table->string('target_type', 30);
            $table->string('target_id', 40);
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('issue_authority_links');
        Schema::dropIfExists('authorities');
        Schema::dropIfExists('observations');
        Schema::dropIfExists('evidence');
        Schema::dropIfExists('status_events');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('admin_areas');
        Schema::dropIfExists('otp_codes');
    }
};