<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('qualifying_minutes')->nullable()->after('discount_value');
            $table->unsignedSmallInteger('bonus_minutes')->nullable()->after('qualifying_minutes');
            $table->unsignedInteger('max_redemptions')->nullable()->after('bonus_minutes');
            $table->boolean('allow_loyalty_stacking')->default(true)->after('max_redemptions');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropColumn([
                'qualifying_minutes',
                'bonus_minutes',
                'max_redemptions',
                'allow_loyalty_stacking',
            ]);
        });
    }
};
