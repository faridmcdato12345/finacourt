<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_id', 26)->unique();
            $table->string('referral_code', 32)->unique();
            $table->string('status', 24)->index();
            $table->text('payout_details')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_partner_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('business_name');
            $table->string('contact_person');
            $table->string('contact_method', 24);
            $table->text('contact_value');
            $table->char('dedupe_hash', 64)->index();
            $table->string('city', 120);
            $table->text('notes')->nullable();
            $table->string('lead_source', 80)->nullable();
            $table->string('status', 32)->index();
            $table->string('conflict_status', 24)->index();
            $table->foreignId('duplicate_of_lead_id')->nullable()->constrained('sales_leads')->nullOnDelete();
            $table->timestamp('protection_started_at')->nullable();
            $table->timestamp('protection_expires_at')->nullable()->index();
            $table->json('onboarding_data')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('status_changed_at');
            $table->timestamps();

            $table->index(['sales_partner_profile_id', 'status', 'created_at'], 'sales_leads_partner_status_idx');
            $table->index(['dedupe_hash', 'protection_expires_at'], 'sales_leads_dedupe_protection_idx');
            $table->index(['organization_id', 'venue_id'], 'sales_leads_inventory_idx');
        });

        Schema::create('sales_partner_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_partner_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_lead_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('referral_code_snapshot', 32);
            $table->string('source', 32);
            $table->timestamp('attributed_at');
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_partner_profile_id', 'activated_at'], 'partner_attr_profile_activated_idx');
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger', 40)->index();
            $table->string('calculation', 24);
            $table->decimal('fixed_amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->boolean('is_active')->default(false)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_partner_profile_id')->constrained()->restrictOnDelete();
            $table->date('period_started_at');
            $table->date('period_ended_at');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->string('status', 24)->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['sales_partner_profile_id', 'status', 'period_ended_at'], 'partner_payout_profile_status_idx');
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_partner_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sales_lead_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sales_partner_attribution_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('partner_payout_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('commission_entries')->restrictOnDelete();
            $table->string('source_type', 40);
            $table->string('source_reference', 120)->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->string('status', 24)->index();
            $table->string('reason', 500);
            $table->json('rule_snapshot')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_partner_profile_id', 'status', 'partner_payout_id'], 'commission_partner_status_payout_idx');
            $table->index(['source_type', 'source_reference'], 'commission_source_idx');
            $table->index(['payment_id', 'status'], 'commission_payment_status_idx');
        });

        Schema::create('partner_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_partner_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commission_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sales_partner_profile_id', 'created_at'], 'partner_audit_profile_date_idx');
            $table->index(['sales_lead_id', 'created_at'], 'partner_audit_lead_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_audit_events');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('partner_payouts');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('sales_partner_attributions');
        Schema::dropIfExists('sales_leads');
        Schema::dropIfExists('sales_partner_profiles');
    }
};
