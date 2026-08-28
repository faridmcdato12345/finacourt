<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')->where('payment_status', 'unpaid')->update(['payment_status' => 'pending']);

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('provider', 40);
            $table->string('mode', 32);
            $table->string('status', 32);
            $table->decimal('amount', 10, 2);
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->char('currency', 3);
            $table->string('provider_reference')->nullable();
            $table->boolean('requires_review')->default(false);
            $table->string('review_reason', 500)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['booking_id', 'created_at']);
            $table->unique(['provider', 'provider_reference']);
        });

        Schema::create('payment_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('source', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_event_id')->nullable()->unique();
            $table->string('note', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'created_at']);
        });

        DB::table('bookings')
            ->whereNotNull('payment_mode')
            ->orderBy('id')
            ->chunkById(100, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $now = now();
                    $reference = 'PAY-'.Str::ulid();
                    $status = $booking->payment_status ?: 'pending';
                    $paymentId = DB::table('payments')->insertGetId([
                        'organization_id' => $booking->organization_id,
                        'booking_id' => $booking->id,
                        'reference' => $reference,
                        'provider' => $booking->payment_mode === 'pay_at_venue' ? 'manual' : 'legacy',
                        'mode' => $booking->payment_mode,
                        'status' => $status,
                        'amount' => $booking->total_amount,
                        'refunded_amount' => 0,
                        'currency' => $booking->currency,
                        'requires_review' => false,
                        'created_by_user_id' => $booking->created_by_user_id,
                        'created_at' => $booking->created_at ?? $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('payment_transitions')->insert([
                        'payment_id' => $paymentId,
                        'from_status' => null,
                        'to_status' => $status,
                        'source' => 'phase6_backfill',
                        'actor_user_id' => $booking->created_by_user_id,
                        'note' => 'Payment attempt backfilled from the Phase 5 booking snapshot.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transitions');
        Schema::dropIfExists('payments');

        DB::table('bookings')
            ->where('payment_mode', 'pay_at_venue')
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'unpaid']);
    }
};
