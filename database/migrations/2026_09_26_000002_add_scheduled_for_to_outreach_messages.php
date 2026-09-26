<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->timestamp('scheduled_for')->nullable()->after('queued_at');
            $table->index(['status', 'scheduled_for'], 'outreach_messages_status_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->dropIndex('outreach_messages_status_schedule_idx');
            $table->dropColumn('scheduled_for');
        });
    }
};
