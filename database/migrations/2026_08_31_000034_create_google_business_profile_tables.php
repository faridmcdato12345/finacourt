<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_business_profile_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('authorized_by_user_id')->nullable();
            $table->foreign('authorized_by_user_id', 'gbp_connections_authorized_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->string('status', 32)->default('not_connected');
            $table->string('match_outcome', 24)->nullable();
            $table->string('google_account_name', 255)->nullable();
            $table->string('google_location_name', 255)->nullable()->unique();
            $table->string('google_account_label')->nullable();
            $table->string('google_account_verification_state', 40)->nullable();
            $table->string('google_location_title')->nullable();
            $table->text('google_location_address')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('candidates')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'gbp_connections_org_status_index');
        });

        Schema::create('google_business_profile_oauth_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('state_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'expires_at'], 'gbp_oauth_states_venue_expiry_index');
        });

        Schema::create('google_business_profile_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()
                ->constrained('google_business_profile_connections')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80);
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['venue_id', 'occurred_at'], 'gbp_audits_venue_time_index');
            $table->index(['organization_id', 'event_type'], 'gbp_audits_org_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_business_profile_audits');
        Schema::dropIfExists('google_business_profile_oauth_states');
        Schema::dropIfExists('google_business_profile_connections');
    }
};
