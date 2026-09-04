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
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->string('payout_type', 16)->default('scheduled')->after('reference')->index();
            $table->decimal('gross_amount', 12, 2)->default(0)->after('amount');
            $table->decimal('payout_fee', 12, 2)->default(0)->after('gross_amount');
            $table->decimal('net_amount', 12, 2)->default(0)->after('payout_fee');
            $table->string('fee_payer', 16)->default('platform')->after('net_amount');
            $table->string('provider', 32)->default('manual')->after('currency');
            $table->string('cycle_key', 160)->nullable()->after('provider')->unique();
            $table->date('scheduled_for')->nullable()->after('period_ended_at');
            // New reconciliations use this internal unique hash. Historical
            // external references remain untouched so the migration is safe
            // even if legacy administrators reused a free-form reference.
            $table->char('reconciliation_key', 64)->nullable()->after('external_reference')->unique();
            $table->timestamp('processing_started_at')->nullable()->after('approved_at');
            $table->timestamp('paid_at')->nullable()->after('sent_at');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('paid_at');
            $table->string('failure_code', 100)->nullable()->after('failed_at');
            $table->text('failure_message')->nullable()->after('failure_code');
            $table->json('metadata')->nullable()->after('note');

            $table->index(
                ['organization_id', 'payout_type', 'status', 'scheduled_for'],
                'owner_payout_org_type_status_schedule_idx',
            );
        });

        DB::table('owner_payouts')->orderBy('id')->chunkById(500, function ($payouts): void {
            foreach ($payouts as $payout) {
                DB::table('owner_payouts')->where('id', $payout->id)->update([
                    'payout_type' => $payout->requested_at ? 'early' : 'scheduled',
                    'gross_amount' => $payout->amount,
                    'net_amount' => $payout->amount,
                    'paid_amount' => $payout->sent_at ? $payout->amount : null,
                    'paid_at' => $payout->sent_at,
                ]);
            }
        });

        // Existing future bookings must not retain the old paid-at-only date.
        // Never move an existing availability date earlier during the backfill.
        DB::table('owner_settlement_entries')
            ->join('bookings', 'bookings.id', '=', 'owner_settlement_entries.booking_id')
            ->where('owner_settlement_entries.type', 'booking_payment')
            ->select([
                'owner_settlement_entries.id',
                'owner_settlement_entries.available_at',
                'bookings.end_at',
            ])
            ->orderBy('owner_settlement_entries.id')
            ->chunkById(500, function ($entries): void {
                $clearingHours = max(0, (int) config('settlements.clearing_hours', 24));

                foreach ($entries as $entry) {
                    $current = Carbon::parse($entry->available_at);
                    $afterBooking = Carbon::parse($entry->end_at)->addHours($clearingHours);

                    if ($afterBooking->greaterThan($current)) {
                        DB::table('owner_settlement_entries')->where('id', $entry->id)->update([
                            'available_at' => $afterBooking,
                        ]);
                    }
                }
            }, 'owner_settlement_entries.id', 'id');
    }

    public function down(): void
    {
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->dropIndex('owner_payout_org_type_status_schedule_idx');
            $table->dropUnique(['cycle_key']);
            $table->dropColumn([
                'payout_type', 'gross_amount', 'payout_fee', 'net_amount',
                'fee_payer', 'provider', 'cycle_key', 'scheduled_for',
                'reconciliation_key', 'processing_started_at', 'paid_at', 'paid_amount',
                'failure_code', 'failure_message', 'metadata',
            ]);
        });
    }
};
