<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reactivation_campaigns', function (Blueprint $table) {
            $table->string('delivery_mode', 20)->default('manual')->after('channel');
            $table->boolean('automation_enabled')->default(false)->after('delivery_mode');
            $table->unsignedSmallInteger('inactivity_days')->nullable()->after('automation_enabled');
            $table->dateTime('automation_last_run_at')->nullable()->after('cancelled_at');
            $table->index(['delivery_mode', 'automation_enabled'], 'reactivation_automation_enabled_idx');
        });

        Schema::table('reactivation_campaign_recipients', function (Blueprint $table) {
            $table->string('inactivity_cycle', 64)->nullable()->after('lifecycle');
            $table->dropUnique('reactivation_recipient_campaign_user_unique');
            $table->unique(
                ['reactivation_campaign_id', 'user_id', 'inactivity_cycle'],
                'reactivation_recipient_campaign_user_cycle_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reactivation_campaign_recipients', function (Blueprint $table) {
            $table->dropUnique('reactivation_recipient_campaign_user_cycle_unique');
            $table->dropColumn('inactivity_cycle');
            $table->unique(['reactivation_campaign_id', 'user_id'], 'reactivation_recipient_campaign_user_unique');
        });

        Schema::table('reactivation_campaigns', function (Blueprint $table) {
            $table->dropIndex('reactivation_automation_enabled_idx');
            $table->dropColumn(['delivery_mode', 'automation_enabled', 'inactivity_days', 'automation_last_run_at']);
        });
    }
};
