<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            // Google permits Place IDs to be stored, but their length is not
            // fixed. Other Places response data is deliberately not persisted.
            $table->text('google_place_id')->nullable()->after('coordinates_verified_at');
            $table->string('google_place_id_source', 40)->nullable()->after('google_place_id');
            $table->timestamp('google_place_id_verified_at')->nullable()->after('google_place_id_source');
        });

        Schema::create('visibility_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destination', 24);
            $table->string('link_key', 64)->unique();
            $table->char('token', 26)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('visits_count')->default(0);
            $table->timestamp('last_visited_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'destination']);
            $table->index(['venue_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visibility_links');

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn([
                'google_place_id',
                'google_place_id_source',
                'google_place_id_verified_at',
            ]);
        });
    }
};
