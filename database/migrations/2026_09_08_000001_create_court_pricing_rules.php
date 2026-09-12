<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('days_of_week');
            $table->time('starts_at_time');
            $table->time('ends_at_time');
            $table->decimal('hourly_rate', 10, 2);
            $table->timestamps();

            $table->index(['resource_id', 'starts_at_time', 'ends_at_time'], 'court_pricing_rules_schedule_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->json('pricing_rule_snapshot')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('pricing_rule_snapshot');
        });

        Schema::dropIfExists('court_pricing_rules');
    }
};
