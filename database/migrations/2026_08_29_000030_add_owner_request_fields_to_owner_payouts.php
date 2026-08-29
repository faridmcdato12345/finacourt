<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('requested_by_user_id');

            $table->index(
                ['organization_id', 'requested_at', 'status'],
                'owner_payout_org_request_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->dropIndex('owner_payout_org_request_status_idx');
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropColumn('requested_at');
        });
    }
};
