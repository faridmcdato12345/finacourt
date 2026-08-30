<?php

namespace App\Payments;

use App\Analytics\AnalyticsRecorder;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingNotifier;
use App\Settlements\OwnerSettlementLedger;
use Illuminate\Validation\ValidationException;

class ApplyPaymentTransition
{
    public function __construct(
        private readonly AnalyticsRecorder $analytics,
        private readonly BookingNotifier $notifications,
        private readonly OwnerSettlementLedger $settlements,
    ) {}

    /**
     * Apply a transition after the caller has locked resource, booking, and payment.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handleLocked(
        Payment $payment,
        Booking $booking,
        PaymentStatus $target,
        string $source,
        ?User $actor = null,
        ?string $externalEventId = null,
        ?string $note = null,
        ?array $metadata = null,
    ): Payment {
        $from = $payment->status;

        if ($from === $target && $externalEventId === null) {
            return $payment;
        }

        if ($from !== $target && ! $this->allows($from, $target)) {
            throw ValidationException::withMessages([
                'payment' => "Payment cannot move from {$from->label()} to {$target->label()}.",
            ]);
        }

        $attributes = ['status' => $target];
        $now = now();

        if ($target === PaymentStatus::Paid) {
            $attributes['paid_at'] = $payment->paid_at ?? $now;
            $attributes['verified_by_user_id'] = $actor?->getKey();

            if ($booking->effectiveStatus() === BookingStatus::Hold) {
                $booking->update(['status' => BookingStatus::Confirmed, 'expires_at' => null]);
            } elseif ($booking->effectiveStatus() !== BookingStatus::Confirmed) {
                $attributes['requires_review'] = true;
                $attributes['review_reason'] = 'Payment was received after the reservation stopped being active.';
            }
        }

        if ($target === PaymentStatus::Failed) {
            $attributes['failed_at'] = $now;
            $this->cancelActiveHold($booking, $actor, 'Payment failed before confirmation.');
        }

        if ($target === PaymentStatus::Cancelled) {
            $attributes['cancelled_at'] = $now;
            $this->cancelActiveHold($booking, $actor, 'Payment was cancelled before confirmation.');
        }

        if ($target === PaymentStatus::Refunded) {
            $attributes['refunded_at'] = $now;
            $attributes['refunded_amount'] = $payment->amount;
        }

        $payment->update($attributes);
        $booking->update(['payment_status' => $target]);
        $payment->transitions()->create([
            'from_status' => $from,
            'to_status' => $target,
            'source' => $source,
            'actor_user_id' => $actor?->getKey(),
            'external_event_id' => $externalEventId,
            'note' => $note,
            'metadata' => $metadata,
        ]);

        if ($target === PaymentStatus::Paid) {
            $this->settlements->recordPaidPayment($payment);
        } elseif ($target === PaymentStatus::Refunded) {
            $this->settlements->recordRefund($payment, $actor);
        }

        if ($target === PaymentStatus::Paid) {
            $booking->refresh();
            $this->analytics->recordBookingCompleted($booking);
            $this->notifications->confirmed($booking);
            $this->notifications->paymentReceived($booking);
        }

        return $payment->refresh();
    }

    private function allows(PaymentStatus $from, PaymentStatus $to): bool
    {
        return match ($from) {
            PaymentStatus::Pending => in_array($to, [
                PaymentStatus::Paid,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true),
            PaymentStatus::Failed, PaymentStatus::Cancelled => $to === PaymentStatus::Paid,
            PaymentStatus::Paid => $to === PaymentStatus::Refunded,
            PaymentStatus::Refunded => false,
        };
    }

    private function cancelActiveHold(Booking $booking, ?User $actor, string $reason): void
    {
        if ($booking->effectiveStatus() !== BookingStatus::Hold) {
            return;
        }

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor?->getKey(),
            'cancellation_reason' => $reason,
        ]);
    }
}
