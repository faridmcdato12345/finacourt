<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->unsignedInteger('loyalty_terms_version')->default(1);
            $table->unsignedTinyInteger('loyalty_stamps_required')->default(5);
            $table->decimal('loyalty_discount_percent', 5, 2)->default(10);
            $table->decimal('loyalty_discount_cap', 12, 2)->default(100);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedInteger('loyalty_terms_version')->nullable();
            $table->unsignedTinyInteger('loyalty_stamps_required')->nullable();
            $table->decimal('loyalty_discount_percent', 5, 2)->nullable();
            $table->decimal('loyalty_discount_cap', 12, 2)->nullable();
        });

        Schema::table('loyalty_stamps', function (Blueprint $table): void {
            $table->unsignedInteger('terms_version')->default(1);
            $table->unsignedTinyInteger('stamps_required')->default(5);
            $table->decimal('discount_percent', 5, 2)->default(10);
            $table->decimal('discount_cap', 12, 2)->default(100);
        });

        Schema::table('loyalty_redemptions', function (Blueprint $table): void {
            $table->unsignedInteger('terms_version')->default(1);
        });

        // The first loyalty release used these exact terms. Preserve the
        // promise attached to bookings that were already made before upgrade.
        DB::table('bookings')->where('loyalty_eligible', true)->update([
            'loyalty_terms_version' => 1,
            'loyalty_stamps_required' => 5,
            'loyalty_discount_percent' => '10.00',
            'loyalty_discount_cap' => '100.00',
        ]);
    }

    public function down(): void
    {
        Schema::table('loyalty_redemptions', fn (Blueprint $table) => $table->dropColumn('terms_version'));
        Schema::table('loyalty_stamps', fn (Blueprint $table) => $table->dropColumn([
            'terms_version', 'stamps_required', 'discount_percent', 'discount_cap',
        ]));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn([
            'loyalty_terms_version', 'loyalty_stamps_required', 'loyalty_discount_percent', 'loyalty_discount_cap',
        ]));
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn([
            'loyalty_terms_version', 'loyalty_stamps_required', 'loyalty_discount_percent', 'loyalty_discount_cap',
        ]));
    }
};
