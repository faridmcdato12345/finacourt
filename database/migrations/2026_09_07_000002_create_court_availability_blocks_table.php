<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64);
            $table->boolean('is_all_day')->default(false);
            $table->string('reason', 500);
            $table->string('series_token', 26)->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'starts_at']);
            $table->index(['resource_id', 'starts_at', 'ends_at'], 'court_blocks_resource_window_index');
            $table->index(['resource_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_availability_blocks');
    }
};
