<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('joined_at');
            $table->foreignId('suspended_by_user_id')->nullable()->after('suspended_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable()->after('suspended_by_user_id');
            $table->foreignId('removed_by_user_id')->nullable()->after('removed_at')
                ->constrained('users')->nullOnDelete();
            $table->index(['organization_id', 'role', 'removed_at']);
        });

        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->json('permissions');
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
            $table->index(['organization_id', 'accepted_at', 'revoked_at']);
            $table->index(['email', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'role', 'removed_at']);
            $table->dropConstrainedForeignId('removed_by_user_id');
            $table->dropColumn('removed_at');
            $table->dropConstrainedForeignId('suspended_by_user_id');
            $table->dropColumn('suspended_at');
        });
    }
};
