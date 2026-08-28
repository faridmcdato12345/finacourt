<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();

            foreach (['first', 'last'] as $touch) {
                $table->string("{$touch}_source", 40);
                $table->string("{$touch}_medium", 64)->nullable();
                $table->string("{$touch}_campaign", 120)->nullable();
                $table->string("{$touch}_referral_code", 80)->nullable();
                $table->string("{$touch}_partner_code", 80)->nullable();
                $table->string("{$touch}_landing_path", 255)->nullable();
                $table->string("{$touch}_referrer_host", 160)->nullable();
                $table->dateTime("{$touch}_seen_at");
            }

            $table->string('attributed_source', 40);
            $table->string('attributed_medium', 64)->nullable();
            $table->string('attributed_campaign', 120)->nullable();
            $table->string('attributed_referral_code', 80)->nullable();
            $table->string('attributed_partner_code', 80)->nullable();
            $table->string('attributed_landing_path', 255)->nullable();
            $table->string('attributed_referrer_host', 160)->nullable();
            $table->dateTime('attributed_at');
            $table->foreignId('promotion_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('promotion_campaign_token', 40)->nullable();
            $table->string('promotion_slot_token', 40)->nullable();
            $table->string('promotion_title')->nullable();
            $table->string('rule_version', 48);
            $table->timestamps();

            $table->index(
                ['organization_id', 'attributed_source', 'created_at'],
                'booking_attr_org_source_date_idx',
            );
            $table->index(
                ['organization_id', 'venue_id', 'attributed_source', 'created_at'],
                'booking_attr_org_venue_source_date_idx',
            );
            $table->index('promotion_campaign_token', 'booking_attr_campaign_token_idx');
            $table->index('promotion_slot_token', 'booking_attr_slot_token_idx');
        });

        $source = <<<'SQL'
            CASE
                WHEN bookings.traffic_source = 'promotion' THEN 'marketplace_promotion'
                WHEN bookings.traffic_source = 'campaign' THEN 'unknown'
                WHEN bookings.traffic_source IN (
                    'marketplace_organic', 'marketplace_promotion', 'google_organic',
                    'google_maps', 'facebook', 'instagram', 'tiktok', 'qr_code',
                    'referral', 'sales_partner', 'direct', 'unknown'
                ) THEN bookings.traffic_source
                WHEN bookings.traffic_source IS NULL THEN 'direct'
                ELSE 'unknown'
            END
            SQL;

        DB::statement(<<<SQL
            INSERT INTO booking_attributions (
                booking_id, organization_id, venue_id,
                first_source, first_campaign, first_seen_at,
                last_source, last_campaign, last_seen_at,
                attributed_source, attributed_campaign, attributed_at,
                promotion_id, promotion_campaign_token, promotion_title,
                rule_version, created_at, updated_at
            )
            SELECT
                bookings.id, bookings.organization_id, bookings.venue_id,
                {$source}, bookings.traffic_source_detail, bookings.created_at,
                {$source}, bookings.traffic_source_detail, bookings.created_at,
                {$source}, COALESCE(bookings.promotion_campaign_token, bookings.traffic_source_detail), bookings.created_at,
                bookings.promotion_id, bookings.promotion_campaign_token, bookings.promotion_title,
                'legacy_backfill_v1', bookings.created_at, bookings.updated_at
            FROM bookings
            WHERE bookings.source = 'marketplace'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_attributions');
    }
};
