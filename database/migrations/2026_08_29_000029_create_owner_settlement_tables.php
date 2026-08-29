<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_payout_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method', 32);
            $table->string('account_name');
            $table->text('details');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('status', 24)->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->date('period_started_at');
            $table->date('period_ended_at');
            $table->text('destination_snapshot');
            $table->string('external_reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'period_ended_at'], 'owner_payout_org_status_period_idx');
        });

        Schema::create('owner_settlement_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_payout_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->string('source_key')->unique();
            $table->string('description');
            $table->timestamp('occurred_at');
            $table->timestamp('available_at');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'currency', 'owner_payout_id', 'available_at'], 'owner_settlement_available_idx');
            $table->index(['payment_id', 'type'], 'owner_settlement_payment_type_idx');
            $table->index(['owner_payout_id', 'occurred_at'], 'owner_settlement_payout_date_idx');
        });

        Schema::create('owner_payout_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_payout_id', 'created_at'], 'owner_payout_event_history_idx');
            $table->index(['organization_id', 'created_at'], 'owner_payout_event_org_idx');
        });

        // Preserve legitimate online earnings already collected before this
        // ledger existed. Manual/pay-at-venue payments are deliberately absent.
        DB::table('payments')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.mode', 'hosted_checkout')
            ->where('payments.status', 'paid')
            ->where('payments.requires_review', false)
            ->where('payments.venue_amount', '>', 0)
            ->select([
                'payments.id as payment_id',
                'payments.organization_id',
                'payments.booking_id',
                'payments.reference as payment_reference',
                'payments.provider',
                'payments.venue_amount',
                'payments.currency',
                'payments.paid_at',
                'payments.updated_at',
                'bookings.reference as booking_reference',
            ])
            ->orderBy('payments.id')
            ->chunkById(500, function ($payments): void {
                $delayDays = max(0, (int) config('settlements.availability_delay_days', 2));
                $now = now();
                $rows = $payments->map(function ($payment) use ($delayDays, $now): array {
                    $occurredAt = Carbon::parse($payment->paid_at ?: $payment->updated_at ?: $now);

                    return [
                        'organization_id' => $payment->organization_id,
                        'payment_id' => $payment->payment_id,
                        'booking_id' => $payment->booking_id,
                        'owner_payout_id' => null,
                        'type' => 'booking_payment',
                        'amount' => $payment->venue_amount,
                        'currency' => strtoupper($payment->currency),
                        'source_key' => "payment:{$payment->payment_id}:venue-paid",
                        'description' => "Court earnings from {$payment->booking_reference}",
                        'occurred_at' => $occurredAt,
                        'available_at' => $occurredAt->copy()->addDays($delayDays),
                        'metadata' => json_encode([
                            'payment_reference' => $payment->payment_reference,
                            'booking_reference' => $payment->booking_reference,
                            'provider' => $payment->provider,
                            'backfilled' => true,
                        ], JSON_THROW_ON_ERROR),
                        'created_by_user_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DB::table('owner_settlement_entries')->insertOrIgnore($rows);
            }, 'payments.id', 'payment_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payout_events');
        Schema::dropIfExists('owner_settlement_entries');
        Schema::dropIfExists('owner_payouts');
        Schema::dropIfExists('owner_payout_profiles');
    }
};
