<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('resource_type', 32)->default('court');
            $table->string('setting', 32);
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('base_hourly_rate', 10, 2);
            $table->char('currency', 3)->default('PHP');
            $table->unsignedSmallInteger('booking_increment_minutes')->default(60);
            $table->timestamps();

            $table->unique(['venue_id', 'name']);
            $table->index(['venue_id', 'sport_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
