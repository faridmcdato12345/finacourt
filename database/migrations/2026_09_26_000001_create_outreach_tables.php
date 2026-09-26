<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_directory_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_claim_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('venue_name');
            $table->string('email', 320)->unique();
            $table->text('private_link');
            $table->string('status', 32)->default('new');
            $table->timestamp('initial_sent_at')->nullable();
            $table->timestamp('followup_1_sent_at')->nullable();
            $table->timestamp('followup_2_sent_at')->nullable();
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'next_send_at'],
                'outreach_leads_status_next_send_idx',
            );
            $table->index(
                ['initial_sent_at', 'followup_1_sent_at', 'followup_2_sent_at'],
                'outreach_leads_campaign_steps_idx',
            );
            $table->index(
                ['replied_at', 'claimed_at', 'unsubscribed_at', 'bounced_at'],
                'outreach_leads_suppression_idx',
            );
        });

        Schema::create('outreach_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outreach_lead_id')->constrained()->cascadeOnDelete();
            $table->string('message_type', 24);
            $table->string('status', 24)->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['outreach_lead_id', 'message_type'],
                'outreach_messages_lead_type_unique',
            );
            $table->index(['status', 'queued_at'], 'outreach_messages_status_queue_idx');
            $table->index(['sent_at', 'message_type'], 'outreach_messages_sent_type_idx');
        });

        Schema::create('outreach_daily_quotas', function (Blueprint $table): void {
            $table->id();
            $table->date('quota_date')->unique();
            $table->unsignedInteger('reserved_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_daily_quotas');
        Schema::dropIfExists('outreach_messages');
        Schema::dropIfExists('outreach_leads');
    }
};
