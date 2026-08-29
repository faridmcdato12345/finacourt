<?php

namespace App\Settlements;

use App\Enums\OwnerPayoutStatus;
use App\Enums\OwnerSettlementEntryType;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\OwnerPayout;
use App\Models\OwnerSettlementEntry;
use App\Models\Payment;
use App\Models\User;

class OwnerSettlementLedger
{
    public function recordPaidPayment(Payment $payment): ?OwnerSettlementEntry
    {
        if (
            $payment->mode !== PaymentMode::HostedCheckout
            || $payment->status !== PaymentStatus::Paid
            || $payment->requires_review
            || $payment->provider === 'manual'
            || $this->cents($payment->venue_amount) <= 0
        ) {
            return null;
        }

        $booking = $payment->booking()->firstOrFail();

        if ($booking->organization_id !== $payment->organization_id) {
            throw new \LogicException('Payment and booking organizations do not match.');
        }

        $paidAt = $payment->paid_at ?? now();

        return OwnerSettlementEntry::query()->firstOrCreate(
            ['source_key' => "payment:{$payment->getKey()}:venue-paid"],
            [
                'organization_id' => $payment->organization_id,
                'payment_id' => $payment->getKey(),
                'booking_id' => $booking->getKey(),
                'type' => OwnerSettlementEntryType::BookingPayment,
                'amount' => $this->money($this->cents($payment->venue_amount)),
                'currency' => strtoupper($payment->currency),
                'description' => "Court earnings from {$booking->reference}",
                'occurred_at' => $paidAt,
                'available_at' => $paidAt->addDays((int) config('settlements.availability_delay_days', 2)),
                'metadata' => [
                    'payment_reference' => $payment->reference,
                    'booking_reference' => $booking->reference,
                    'provider' => $payment->provider,
                ],
            ],
        );
    }

    public function recordRefund(Payment $payment, ?User $actor = null): ?OwnerSettlementEntry
    {
        if ($payment->mode !== PaymentMode::HostedCheckout || $payment->status !== PaymentStatus::Refunded) {
            return null;
        }

        $credit = OwnerSettlementEntry::query()
            ->where('source_key', "payment:{$payment->getKey()}:venue-paid")
            ->lockForUpdate()
            ->first();

        if (! $credit) {
            return null;
        }

        $adjustment = OwnerSettlementEntry::query()->firstOrCreate(
            ['source_key' => "payment:{$payment->getKey()}:venue-refunded"],
            [
                'organization_id' => $payment->organization_id,
                'payment_id' => $payment->getKey(),
                'booking_id' => $payment->booking_id,
                'owner_payout_id' => $this->editablePayoutId($credit),
                'type' => OwnerSettlementEntryType::RefundAdjustment,
                'amount' => $this->money(-$this->cents($payment->venue_amount)),
                'currency' => strtoupper($payment->currency),
                'description' => "Refund for {$payment->reference}",
                'occurred_at' => $payment->refunded_at ?? now(),
                'available_at' => now(),
                'metadata' => [
                    'payment_reference' => $payment->reference,
                    'refunded_amount' => $payment->refunded_amount,
                    'allocation' => 'full_venue_amount',
                ],
                'created_by_user_id' => $actor?->getKey(),
            ],
        );

        if ($adjustment->wasRecentlyCreated && $adjustment->owner_payout_id !== null) {
            $this->recalculateOpenPayout($adjustment->owner_payout_id, $actor, $payment->reference);
        }

        return $adjustment;
    }

    private function editablePayoutId(OwnerSettlementEntry $credit): ?int
    {
        if ($credit->owner_payout_id === null) {
            return null;
        }

        return OwnerPayout::query()
            ->whereKey($credit->owner_payout_id)
            ->whereIn('status', [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved])
            ->value('id');
    }

    private function recalculateOpenPayout(int $payoutId, ?User $actor, string $paymentReference): void
    {
        $payout = OwnerPayout::query()->whereKey($payoutId)->lockForUpdate()->firstOrFail();
        $newAmountCents = $payout->entries()->get()->sum(
            fn (OwnerSettlementEntry $entry): int => $this->cents($entry->amount),
        );

        if ($newAmountCents > 0) {
            $payout->update(['amount' => $this->money($newAmountCents)]);
            $payout->events()->create([
                'organization_id' => $payout->organization_id,
                'actor_user_id' => $actor?->getKey(),
                'action' => 'refund_recalculated',
                'from_status' => $payout->status,
                'to_status' => $payout->status,
                'note' => "Payout total updated after refund {$paymentReference}.",
                'metadata' => ['amount' => $this->money($newAmountCents)],
            ]);

            return;
        }

        $from = $payout->status;
        $payout->update([
            'status' => OwnerPayoutStatus::Cancelled,
            'cancelled_at' => now(),
            'note' => 'Cancelled automatically because refunds reduced the payout to zero.',
        ]);
        $payout->entries()->update(['owner_payout_id' => null]);
        $payout->events()->create([
            'organization_id' => $payout->organization_id,
            'actor_user_id' => $actor?->getKey(),
            'action' => 'cancelled_after_refund',
            'from_status' => $from,
            'to_status' => OwnerPayoutStatus::Cancelled,
            'note' => "Refund {$paymentReference} reduced the payout to zero.",
        ]);
    }

    private function cents(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
