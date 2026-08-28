<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_service_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('fee_type', 32);
            $table->unsignedInteger('percentage_basis_points')->nullable();
            $table->decimal('fixed_amount', 10, 2)->nullable();
            $table->decimal('minimum_fee_amount', 10, 2)->default(0);
            $table->decimal('maximum_fee_amount', 10, 2)->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->boolean('is_active')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('deactivated_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'currency', 'starts_at', 'ends_at'], 'platform_fee_active_currency_window_idx');
            $table->index(['created_by_user_id', 'created_at'], 'platform_fee_creator_created_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('platform_service_fee_rule_id')
                ->nullable()
                ->after('discount_amount')
                ->constrained('platform_service_fee_rules')
                ->restrictOnDelete();
            $table->string('platform_service_fee_name')->nullable()->after('platform_service_fee_rule_id');
            $table->string('platform_service_fee_type', 32)->nullable()->after('platform_service_fee_name');
            $table->unsignedInteger('platform_service_fee_rate_basis_points')->nullable()->after('platform_service_fee_type');
            $table->decimal('platform_service_fee_fixed_amount', 10, 2)->nullable()->after('platform_service_fee_rate_basis_points');
            $table->decimal('platform_service_fee_amount', 10, 2)->default(0)->after('platform_service_fee_fixed_amount');
            $table->decimal('player_total_amount', 10, 2)->default(0)->after('platform_service_fee_amount');

            $table->index(['platform_service_fee_rule_id', 'created_at'], 'bookings_platform_fee_rule_created_idx');
            $table->index(['source', 'status', 'payment_status', 'created_at'], 'bookings_source_status_payment_created_idx');
        });

        DB::table('bookings')->update([
            'platform_service_fee_amount' => '0.00',
            'player_total_amount' => DB::raw('total_amount'),
        ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('venue_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('platform_service_fee_amount', 10, 2)->default(0)->after('venue_amount');
        });

        DB::table('payments')->update([
            'venue_amount' => DB::raw('amount'),
            'platform_service_fee_amount' => '0.00',
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['venue_amount', 'platform_service_fee_amount']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['platform_service_fee_rule_id']);
            $table->dropIndex('bookings_platform_fee_rule_created_idx');
            $table->dropIndex('bookings_source_status_payment_created_idx');
            $table->dropColumn([
                'platform_service_fee_rule_id',
                'platform_service_fee_name',
                'platform_service_fee_type',
                'platform_service_fee_rate_basis_points',
                'platform_service_fee_fixed_amount',
                'platform_service_fee_amount',
                'player_total_amount',
            ]);
        });

        Schema::dropIfExists('platform_service_fee_rules');
    }
};
