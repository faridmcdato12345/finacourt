<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_offer_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('reactivation_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('minimum_spend', 12, 2)->nullable();
            $table->unsignedSmallInteger('valid_days');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('reactivation_campaign_id', 'customer_offer_campaign_reactivation_unique');
            $table->index(['organization_id', 'venue_id', 'is_active'], 'customer_offer_campaign_venue_active_idx');
        });

        Schema::create('customer_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_offer_campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reactivation_campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20)->default('available');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('minimum_spend', 12, 2)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('reservation_expires_at')->nullable();
            $table->dateTime('redeemed_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique('reactivation_campaign_recipient_id', 'customer_offer_recipient_unique');
            $table->index(['venue_id', 'user_id', 'status', 'expires_at'], 'customer_offer_customer_available_idx');
            $table->index(['customer_offer_campaign_id', 'status'], 'customer_offer_campaign_status_idx');
            $table->index(['booking_id', 'status'], 'customer_offer_booking_status_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('customer_offer_id')->nullable()->after('promotion_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_offer_campaign_id')->nullable()->after('customer_offer_id')->constrained()->restrictOnDelete();
            $table->string('customer_offer_label')->nullable()->after('promotion_title');
            $table->decimal('customer_offer_discount_amount', 12, 2)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_offer_id');
            $table->dropConstrainedForeignId('customer_offer_campaign_id');
            $table->dropColumn(['customer_offer_label', 'customer_offer_discount_amount']);
        });
        Schema::dropIfExists('customer_offers');
        Schema::dropIfExists('customer_offer_campaigns');
    }
};
