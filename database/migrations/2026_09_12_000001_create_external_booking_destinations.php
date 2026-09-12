<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_booking_destinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_name', 120)->nullable();
            $table->text('destination_url');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(
                ['organization_id', 'is_active'],
                'external_booking_destinations_org_active_idx',
            );
        });

        Schema::table('visibility_links', function (Blueprint $table): void {
            $table->foreignId('external_booking_destination_id')
                ->nullable()
                ->after('promotion_id')
                ->constrained('external_booking_destinations')
                ->nullOnDelete();
            $table->string('label', 120)->nullable()->after('acquisition_source');
            $table->string('campaign', 120)->nullable()->after('label');
            $table->softDeletes();

            $table->index(
                ['external_booking_destination_id', 'is_active'],
                'visibility_links_external_destination_active_idx',
            );
        });

        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->foreignId('visibility_link_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('visibility_links')
                ->nullOnDelete();
            $table->index(
                ['visibility_link_id', 'event_type', 'occurred_at'],
                'analytics_visibility_link_type_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropForeign(['visibility_link_id']);
            $table->dropIndex('analytics_visibility_link_type_date_idx');
            $table->dropColumn('visibility_link_id');
        });

        Schema::table('visibility_links', function (Blueprint $table): void {
            $table->dropForeign(['external_booking_destination_id']);
            $table->dropIndex('visibility_links_external_destination_active_idx');
            $table->dropColumn([
                'external_booking_destination_id',
                'label',
                'campaign',
                'deleted_at',
            ]);
        });

        Schema::dropIfExists('external_booking_destinations');
    }
};
