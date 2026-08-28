<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['player_user_id', 'start_at'], 'bookings_player_start_idx');
            $table->index(['source', 'status', 'created_at'], 'bookings_source_status_created_idx');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->index(['is_published', 'verified_at', 'updated_at'], 'venues_public_featured_idx');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->index(
                ['is_active', 'is_public', 'starts_on', 'ends_on'],
                'promotions_public_schedule_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // MySQL may adopt the composite player index for the existing
            // player_user_id foreign key and remove its redundant implicit
            // index. Restore a single-column supporting index before dropping
            // the pilot composite index.
            $table->index('player_user_id', 'bookings_player_user_fk_idx');
            $table->dropIndex('bookings_player_start_idx');
            $table->dropIndex('bookings_source_status_created_idx');
        });
        Schema::table('venues', fn (Blueprint $table) => $table->dropIndex('venues_public_featured_idx'));
        Schema::table('promotions', fn (Blueprint $table) => $table->dropIndex('promotions_public_schedule_idx'));
    }
};
