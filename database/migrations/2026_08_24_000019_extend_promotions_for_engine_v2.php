<?php

use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreignId('audience_sport_id')
                ->nullable()
                ->after('resource_id')
                ->constrained('sports')
                ->restrictOnDelete();
            $table->string('audience_city_slug', 120)->nullable()->after('audience_sport_id');
            $table->string('goal', 48)
                ->default(PromotionGoal::FillEmptySlots->value)
                ->after('promotion_type');
            $table->string('status', 24)
                ->default(PromotionStatus::Draft->value)
                ->after('goal');
            $table->boolean('targets_specific_slots')->default(false)->after('ends_on');

            $table->index(
                ['status', 'is_public', 'starts_on', 'ends_on'],
                'promotions_marketplace_status_idx',
            );
            $table->index(['organization_id', 'goal', 'status'], 'promotions_goal_status_idx');
            $table->index(
                ['audience_city_slug', 'audience_sport_id', 'status'],
                'promotions_audience_idx',
            );
        });

        DB::table('promotions')->where('is_active', true)->update([
            'status' => PromotionStatus::Active->value,
        ]);
        DB::table('promotions')->where('is_active', false)->update([
            'status' => PromotionStatus::Draft->value,
        ]);
        DB::statement(<<<'SQL'
            UPDATE promotions
            INNER JOIN venues ON venues.id = promotions.venue_id
            SET promotions.audience_city_slug = venues.city_slug
            WHERE promotions.audience_city_slug IS NULL
            SQL);

        Schema::create('promotion_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->restrictOnDelete();
            $table->string('slot_token', 40)->unique();
            $table->date('slot_date');
            $table->time('starts_at_time');
            $table->time('ends_at_time');
            $table->timestamps();

            $table->unique(
                ['promotion_id', 'resource_id', 'slot_date', 'starts_at_time', 'ends_at_time'],
                'promotion_slots_campaign_window_unique',
            );
            $table->index(
                ['resource_id', 'slot_date', 'starts_at_time', 'ends_at_time'],
                'promotion_slots_resource_window_idx',
            );
            $table->index(['promotion_id', 'slot_date'], 'promotion_slots_campaign_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_slots');

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex('promotions_marketplace_status_idx');
            $table->dropIndex('promotions_goal_status_idx');
            $table->dropIndex('promotions_audience_idx');
            $table->dropForeign(['audience_sport_id']);
            $table->dropColumn([
                'audience_sport_id',
                'audience_city_slug',
                'goal',
                'status',
                'targets_specific_slots',
            ]);
        });
    }
};
