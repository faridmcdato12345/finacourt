<?php

namespace App\Settlements;

use App\Enums\BookingStatus;
use App\Enums\OwnerPayoutStatus;
use App\Enums\OwnerSettlementEntryType;
use App\Enums\PaymentStatus;
use App\Models\OwnerPayout;
use App\Models\OwnerSettlementEntry;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class OwnerBalanceService
{
    /** @return Builder<OwnerSettlementEntry> */
    public function availableEntriesQuery(
        int $organizationId,
        string $currency = 'PHP',
        ?CarbonInterface $at = null,
    ): Builder {
        $at ??= now();

        return OwnerSettlementEntry::query()
            ->where('organization_id', $organizationId)
            ->where('currency', strtoupper($currency))
            ->whereNull('owner_payout_id')
            ->where('available_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query->where(function (Builder $query) use ($at): void {
                    $query->where('type', OwnerSettlementEntryType::BookingPayment)
                        ->whereHas('payment', fn (Builder $payment) => $payment
                            ->where('status', PaymentStatus::Paid)
                            ->where('requires_review', false))
                        ->whereHas('booking', fn (Builder $booking) => $booking
                            ->where('status', BookingStatus::Confirmed)
                            ->whereNull('cancelled_at')
                            ->where('end_at', '<=', $at));
                })->orWhereIn('type', [
                    OwnerSettlementEntryType::PayoutReversal,
                    OwnerSettlementEntryType::AdminAdjustment,
                ])->orWhere(function (Builder $query): void {
                    // A refund becomes a recoverable balance only when its
                    // original earning was already paid out. Before payout,
                    // the refunded payment and its credit are simply ineligible.
                    $query->where('type', OwnerSettlementEntryType::RefundAdjustment)
                        ->whereHas('payment.settlementEntries', fn (Builder $credit) => $credit
                            ->where('type', OwnerSettlementEntryType::BookingPayment)
                            ->whereHas('payout', fn (Builder $payout) => $payout->whereIn('status', [
                                OwnerPayoutStatus::Paid,
                                OwnerPayoutStatus::Sent,
                                OwnerPayoutStatus::Reversed,
                            ])));
                });
            });
    }

    /** @return Builder<OwnerSettlementEntry> */
    public function pendingEntriesQuery(
        int $organizationId,
        string $currency = 'PHP',
        ?CarbonInterface $at = null,
    ): Builder {
        $at ??= now();

        return OwnerSettlementEntry::query()
            ->where('organization_id', $organizationId)
            ->where('currency', strtoupper($currency))
            ->whereNull('owner_payout_id')
            ->where('type', OwnerSettlementEntryType::BookingPayment)
            ->whereHas('payment', fn (Builder $payment) => $payment->where('status', PaymentStatus::Paid))
            ->whereHas('booking', fn (Builder $booking) => $booking
                ->where('status', BookingStatus::Confirmed)
                ->whereNull('cancelled_at'))
            ->where(function (Builder $query) use ($at): void {
                $query->where('available_at', '>', $at)
                    ->orWhereHas('payment', fn (Builder $payment) => $payment->where('requires_review', true));
            });
    }

    /** @return array{pending: int, available: int, processing: int, paid: int} */
    public function balances(int $organizationId, string $currency = 'PHP', ?CarbonInterface $at = null): array
    {
        $at ??= now();

        return [
            'pending' => $this->sum($this->pendingEntriesQuery($organizationId, $currency, $at)),
            'available' => $this->sum($this->availableEntriesQuery($organizationId, $currency, $at)),
            'processing' => $this->sumPayouts($organizationId, $currency, [
                OwnerPayoutStatus::Pending,
                OwnerPayoutStatus::Approved,
                OwnerPayoutStatus::Processing,
            ], 'gross_amount'),
            'paid' => $this->sumPayouts($organizationId, $currency, [
                OwnerPayoutStatus::Paid,
                OwnerPayoutStatus::Sent,
            ], 'net_amount'),
        ];
    }

    /** @param Builder<OwnerSettlementEntry> $query */
    public function sum(Builder $query): int
    {
        return $query->get(['amount'])->sum(
            fn (OwnerSettlementEntry $entry): int => Money::cents($entry->amount),
        );
    }

    /** @param array<int, OwnerPayoutStatus> $statuses */
    private function sumPayouts(int $organizationId, string $currency, array $statuses, string $column): int
    {
        return OwnerPayout::query()
            ->where('organization_id', $organizationId)
            ->where('currency', strtoupper($currency))
            ->whereIn('status', $statuses)
            ->get([$column])
            ->sum(fn (OwnerPayout $payout): int => Money::cents($payout->{$column}));
    }
}
