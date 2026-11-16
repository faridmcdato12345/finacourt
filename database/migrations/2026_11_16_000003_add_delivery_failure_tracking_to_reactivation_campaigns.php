<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reactivation_campaigns', function (Blueprint $table) {
            $table->unsignedInteger('failed_count')->default(0)->after('delivered_count');
        });

        Schema::table('reactivation_campaign_recipients', function (Blueprint $table) {
            $table->dateTime('delivery_failed_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('reactivation_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn('delivery_failed_at');
        });

        Schema::table('reactivation_campaigns', function (Blueprint $table) {
            $table->dropColumn('failed_count');
        });
    }
};
