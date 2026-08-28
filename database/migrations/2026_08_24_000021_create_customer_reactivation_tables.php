<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('marketing_opt_in')->default(false);
            $table->boolean('in_app_marketing_enabled')->default(false);
            $table->dateTime('opted_in_at')->nullable();
            $table->dateTime('opted_out_at')->nullable();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reactivation_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('campaign_token', 40)->unique();
            $table->string('title');
            $table->text('message');
            $table->string('segment', 32);
            $table->string('channel', 24)->default('in_app');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('audience_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('suppressed_count')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'created_at'], 'reactivation_campaign_org_status_idx');
            $table->index(['organization_id', 'venue_id', 'created_at'], 'reactivation_campaign_org_venue_idx');
        });

        Schema::create('reactivation_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reactivation_campaign_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('suggested_resource_id')->nullable();
            $table->string('click_token', 40)->unique();
            $table->string('lifecycle', 24);
            $table->dateTime('last_booking_at')->nullable();
            $table->date('suggested_date')->nullable();
            $table->string('suggested_start_time', 8)->nullable();
            $table->unsignedSmallInteger('suggested_duration_minutes')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('clicked_at')->nullable();
            $table->dateTime('suppressed_at')->nullable();
            $table->string('suppression_reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['reactivation_campaign_id', 'user_id'], 'reactivation_recipient_campaign_user_unique');
            $table->index(['user_id', 'sent_at'], 'reactivation_recipient_user_sent_idx');
            $table->index(['reactivation_campaign_id', 'clicked_at'], 'reactivation_recipient_campaign_click_idx');
            $table->foreign('reactivation_campaign_id', 'reactivation_recipient_campaign_fk')
                ->references('id')->on('reactivation_campaigns')->cascadeOnDelete();
            $table->foreign('suggested_resource_id', 'reactivation_recipient_resource_fk')
                ->references('id')->on('resources')->nullOnDelete();
        });

        Schema::table('booking_attributions', function (Blueprint $table) {
            $table->foreignId('reactivation_campaign_id')
                ->nullable()
                ->after('promotion_title')
                ->constrained()
                ->restrictOnDelete();
            $table->string('reactivation_campaign_token', 40)->nullable()->after('reactivation_campaign_id');
            $table->string('reactivation_campaign_title')->nullable()->after('reactivation_campaign_token');
            $table->index(
                ['organization_id', 'reactivation_campaign_id', 'created_at'],
                'booking_attr_org_reactivation_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('booking_attributions', function (Blueprint $table) {
            $table->dropIndex('booking_attr_org_reactivation_idx');
            $table->dropConstrainedForeignId('reactivation_campaign_id');
            $table->dropColumn(['reactivation_campaign_token', 'reactivation_campaign_title']);
        });
        Schema::dropIfExists('reactivation_campaign_recipients');
        Schema::dropIfExists('reactivation_campaigns');
        Schema::dropIfExists('marketing_preferences');
    }
};
