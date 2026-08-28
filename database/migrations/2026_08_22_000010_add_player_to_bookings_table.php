<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('player_user_id')
                ->nullable()
                ->after('resource_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('payment_mode', 32)->nullable()->after('currency');
            $table->string('payment_status', 32)->nullable()->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_user_id');
            $table->dropColumn(['payment_mode', 'payment_status']);
        });
    }
};
