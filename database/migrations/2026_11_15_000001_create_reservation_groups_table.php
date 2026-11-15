<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('player_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('status', 32);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('reservation_group_id')->nullable()->after('organization_id')
                ->constrained('reservation_groups')->nullOnDelete();
            $table->index('reservation_group_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('reservation_group_id')->nullable()->after('organization_id')
                ->constrained('reservation_groups')->nullOnDelete();
            $table->index('reservation_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_group_id');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_group_id');
        });
        Schema::dropIfExists('reservation_groups');
    }
};
