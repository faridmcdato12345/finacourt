<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->string('coordinates_source', 40)->nullable()->after('longitude');
            $table->timestamp('coordinates_verified_at')->nullable()->after('coordinates_source');
            $table->index('coordinates_verified_at');
        });

        DB::table('venues')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->update([
                'coordinates_source' => 'existing',
                'coordinates_verified_at' => now(),
            ]);

        Schema::create('venue_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('moderation_note', 500)->nullable();
            $table->timestamps();

            $table->unique('booking_id');
            $table->index(['venue_id', 'status', 'published_at']);
            $table->index(['organization_id', 'status']);
            $table->index(['player_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_reviews');

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropIndex(['coordinates_verified_at']);
            $table->dropColumn(['coordinates_source', 'coordinates_verified_at']);
        });
    }
};
