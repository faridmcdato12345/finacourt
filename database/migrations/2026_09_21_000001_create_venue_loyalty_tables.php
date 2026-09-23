<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->boolean('loyalty_active')->default(false);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            // Snapshot the owner's promise at checkout; pausing the program later
            // must not take a stamp away from an already eligible booking.
            $table->boolean('loyalty_eligible')->default(false);
            $table->timestamp('loyalty_processed_at')->nullable();
            $table->index(['loyalty_eligible', 'loyalty_processed_at', 'end_at']);
        });

        Schema::create('loyalty_stamps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('local_play_date');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'player_user_id', 'reversed_at']);
            $table->index(['venue_id', 'player_user_id', 'local_play_date']);
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('court_discount_amount', 12, 2);
            $table->timestamps();
            $table->index(['venue_id', 'player_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
        Schema::dropIfExists('loyalty_stamps');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['loyalty_eligible', 'loyalty_processed_at', 'end_at']);
            $table->dropColumn(['loyalty_eligible', 'loyalty_processed_at']);
        });
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn('loyalty_active'));
    }
};
