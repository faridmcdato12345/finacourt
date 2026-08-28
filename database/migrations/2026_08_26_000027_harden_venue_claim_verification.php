<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_claim_requests', function (Blueprint $table): void {
            $table->foreignId('proof_verified_by_user_id')
                ->nullable()
                ->after('reviewed_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('proof_status', 24)->default('pending')->after('status');
            $table->string('proof_method', 40)->nullable()->after('proof_status');
            $table->string('proof_destination', 160)->nullable()->after('proof_method');
            $table->string('proof_code_hash')->nullable()->after('proof_destination');
            $table->timestamp('proof_code_expires_at')->nullable()->after('proof_code_hash');
            $table->unsignedTinyInteger('proof_attempts')->default(0)->after('proof_code_expires_at');
            $table->timestamp('proof_sent_at')->nullable()->after('proof_attempts');
            $table->timestamp('proof_verified_at')->nullable()->after('proof_sent_at');
            $table->text('proof_notes')->nullable()->after('proof_verified_at');
            $table->timestamp('approval_available_at')->nullable()->after('proof_notes');

            $table->index(['proof_status', 'approval_available_at'], 'claims_proof_approval_idx');
        });

        // Preserve the audit meaning of claims approved before the stronger
        // proof workflow existed without treating their venues as marketplace
        // verified. Those venues still require the new platform review gate.
        DB::table('venue_claim_requests')
            ->where('status', 'approved')
            ->update([
                'proof_status' => 'verified',
                'proof_method' => 'legacy_admin_review',
                'proof_verified_at' => DB::raw('COALESCE(reviewed_at, updated_at, created_at)'),
                'approval_available_at' => DB::raw('COALESCE(reviewed_at, updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('venue_claim_requests', function (Blueprint $table): void {
            $table->dropForeign(['proof_verified_by_user_id']);
            $table->dropIndex('claims_proof_approval_idx');
            $table->dropColumn([
                'proof_verified_by_user_id',
                'proof_status',
                'proof_method',
                'proof_destination',
                'proof_code_hash',
                'proof_code_expires_at',
                'proof_attempts',
                'proof_sent_at',
                'proof_verified_at',
                'proof_notes',
                'approval_available_at',
            ]);
        });
    }
};
