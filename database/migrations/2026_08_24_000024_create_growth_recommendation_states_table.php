<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_recommendation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->char('recommendation_key', 64);
            $table->string('recommendation_type', 64);
            $table->string('status', 24);
            $table->dateTime('snoozed_until')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'recommendation_key'],
                'growth_state_org_recommendation_unique',
            );
            $table->index(
                ['organization_id', 'status', 'snoozed_until'],
                'growth_state_org_status_snooze_idx',
            );
            $table->index(
                ['organization_id', 'venue_id', 'recommendation_type'],
                'growth_state_org_venue_type_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_recommendation_states');
    }
};
