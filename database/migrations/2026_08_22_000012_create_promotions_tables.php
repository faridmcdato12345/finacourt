<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->restrictOnDelete();
            $table->string('campaign_token', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('promotion_type', 32);
            $table->string('discount_type', 32)->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('days_of_week')->nullable();
            $table->time('starts_at_time')->nullable();
            $table->time('ends_at_time')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->unsignedBigInteger('booking_starts_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['venue_id', 'is_active', 'is_public']);
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('promotion_id')
                ->nullable()
                ->after('resource_id')
                ->constrained('promotions')
                ->restrictOnDelete();
            $table->string('promotion_campaign_token', 40)->nullable()->after('promotion_id');
            $table->string('promotion_title')->nullable()->after('promotion_campaign_token');
            $table->decimal('original_unit_price', 10, 2)->nullable()->after('unit_price');
            $table->decimal('original_total_amount', 10, 2)->nullable()->after('total_amount');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_total_amount');

            $table->index(['promotion_id', 'created_at']);
            $table->index('promotion_campaign_token');
        });

        DB::table('bookings')->update([
            'original_unit_price' => DB::raw('unit_price'),
            'original_total_amount' => DB::raw('total_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropIndex(['promotion_id', 'created_at']);
            $table->dropIndex(['promotion_campaign_token']);
            $table->dropColumn([
                'promotion_id',
                'promotion_campaign_token',
                'promotion_title',
                'original_unit_price',
                'original_total_amount',
                'discount_amount',
            ]);
        });

        Schema::dropIfExists('promotions');
    }
};
