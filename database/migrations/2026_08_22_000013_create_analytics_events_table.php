<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 48);
            $table->char('visitor_hash', 64)->nullable();
            $table->string('traffic_source', 40)->default('direct');
            $table->string('source_detail', 160)->nullable();
            $table->char('dedupe_key', 64)->unique();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['event_type', 'occurred_at'], 'analytics_type_date_idx');
            $table->index(['organization_id', 'event_type', 'occurred_at'], 'analytics_org_type_date_idx');
            $table->index(['organization_id', 'occurred_at', 'visitor_hash'], 'analytics_org_date_visitor_idx');
            $table->index(['venue_id', 'event_type', 'occurred_at'], 'analytics_venue_type_date_idx');
            $table->index(['promotion_id', 'event_type', 'occurred_at'], 'analytics_promo_type_date_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('traffic_source', 40)->nullable()->after('source');
            $table->string('traffic_source_detail', 160)->nullable()->after('traffic_source');
            $table->index(['organization_id', 'source', 'created_at'], 'bookings_org_source_created_idx');
            $table->index(['organization_id', 'venue_id', 'source', 'created_at'], 'bookings_org_venue_source_created_idx');
        });
    }

    public function down(): void
    {
        $bookingIndexes = collect(Schema::getIndexes('bookings'))->pluck('name');

        if ($bookingIndexes->contains('bookings_org_source_created_idx')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex('bookings_org_source_created_idx'));
        }

        if ($bookingIndexes->contains('bookings_org_venue_source_created_idx')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex('bookings_org_venue_source_created_idx'));
        }

        // Supports local databases that briefly used the pre-final index name.
        if ($bookingIndexes->contains('bookings_org_venue_created_idx')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex('bookings_org_venue_created_idx'));
        }

        $columns = collect(['traffic_source', 'traffic_source_detail'])
            ->filter(fn (string $column) => Schema::hasColumn('bookings', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn($columns));
        }

        Schema::dropIfExists('analytics_events');
    }
};
