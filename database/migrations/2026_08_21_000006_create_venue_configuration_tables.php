<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_venue', function (Blueprint $table) {
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['sport_id', 'venue_id']);
        });

        Schema::create('amenity_venue', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['amenity_id', 'venue_id']);
        });

        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
            $table->unique(['venue_id', 'day_of_week']);
        });

        Schema::create('venue_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['venue_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_photos');
        Schema::dropIfExists('operating_hours');
        Schema::dropIfExists('amenity_venue');
        Schema::dropIfExists('sport_venue');
    }
};
