<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_directory_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rights_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('claimed_venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->char('public_id', 26)->unique();
            $table->char('directory_key', 64)->unique();
            $table->string('slug')->unique();
            $table->string('status', 24)->default('draft');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('city_slug');
            $table->string('province');
            $table->string('province_slug');
            $table->string('country', 80)->default('Philippines');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('coordinates_verified_at')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('source_type', 40);
            $table->string('source_url', 2048)->nullable();
            $table->string('source_reference', 500)->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamp('rights_confirmed_at');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'city_slug'], 'directory_status_city_idx');
            $table->index(['status', 'last_verified_at'], 'directory_status_verified_idx');
            $table->index(['claimed_venue_id', 'status'], 'directory_claimed_venue_idx');
        });

        Schema::create('sport_venue_directory_listing', function (Blueprint $table): void {
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_directory_listing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['sport_id', 'venue_directory_listing_id'], 'directory_sport_primary');
        });

        Schema::create('venue_directory_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_directory_listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
            $table->unique(['venue_directory_listing_id', 'day_of_week'], 'directory_hours_day_unique');
        });

        Schema::create('venue_claim_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_directory_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->char('active_claim_key', 64)->nullable()->unique();
            $table->string('relationship_to_venue', 40);
            $table->text('verification_contact');
            $table->text('evidence_details');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'created_at'], 'claims_org_status_idx');
            $table->index(['venue_directory_listing_id', 'status'], 'claims_listing_status_idx');
        });

        Schema::create('venue_directory_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_directory_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 24);
            $table->string('status', 24)->default('pending');
            $table->text('contact_email')->nullable();
            $table->text('details');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'directory_reports_status_idx');
        });

        Schema::create('venue_directory_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_directory_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_claim_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 48);
            $table->json('changes')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['venue_directory_listing_id', 'occurred_at'], 'directory_audit_listing_idx');
        });

        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->foreignId('venue_directory_listing_id')
                ->nullable()
                ->after('venue_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(
                ['venue_directory_listing_id', 'event_type', 'occurred_at'],
                'analytics_directory_type_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropForeign(['venue_directory_listing_id']);
            $table->dropIndex('analytics_directory_type_date_idx');
            $table->dropColumn('venue_directory_listing_id');
        });

        Schema::dropIfExists('venue_directory_audits');
        Schema::dropIfExists('venue_directory_reports');
        Schema::dropIfExists('venue_claim_requests');
        Schema::dropIfExists('venue_directory_hours');
        Schema::dropIfExists('sport_venue_directory_listing');
        Schema::dropIfExists('venue_directory_listings');
    }
};
