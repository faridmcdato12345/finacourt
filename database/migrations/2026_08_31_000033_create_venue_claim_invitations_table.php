<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_claim_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_directory_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_claim_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['venue_directory_listing_id', 'used_at', 'revoked_at', 'expires_at'],
                'claim_invitation_listing_state_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_claim_invitations');
    }
};
