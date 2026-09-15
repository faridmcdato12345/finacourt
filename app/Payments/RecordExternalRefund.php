<?php

namespace App\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Refunds\RefundNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordExternalRefund
{
    public function __construct(
        private readonly ApplyPaymentTransition $transitions,
        private readonly RefundNotifier $notifications,
    ) {}

    public function handle(Payment $payment, User $admin, string $externalReference, ?string $note = null): Payment
    {
        abort_unless($admin->is_platform_admin, 403);

        $refundRequest = null;
        $payment = DB::transaction(function () use ($payment, $admin, $externalReference, $note, &$refundRequest): Payment {
            $booking = $payment->booking()->firstOrFail();
            CourtResource::query()->whereKey($booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment)->lockForUpdate()->firstOrFail();
            $refundRequest = RefundRequest::query()
                ->where('payment_id', $payment->getKey())
                ->lockForUpdate()
                ->first();

            if ($payment->mode !== PaymentMode::HostedCheckout || $payment->status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'payment' => 'Only a paid online checkout can be recorded as externally refunded.',
                ]);
            }

            if (
                $booking->start_at->isFuture()
                && in_array($booking->effectiveStatus(), [BookingStatus::Hold, BookingStatus::Confirmed], true)
            ) {
                $booking->update([
                    'status' => BookingStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $admin->getKey(),
                    'cancellation_reason' => 'Full online refund reconciled by FinACourt support.',
                ]);
            }

            $payment->update([
                'requires_review' => false,
                'review_reason' => null,
            ]);
            $payment = $this->transitions->handleLocked(
                $payment,
                $booking,
                PaymentStatus::Refunded,
                'platform_external_refund',
                $admin,
                note: $note ?: 'Full refund completed outside FinACourt.',
                metadata: ['external_refund_reference' => $externalReference],
            );

            if ($refundRequest !== null) {
                $refundRequest->update([
                    'status' => RefundRequestStatus::Refunded,
                    'reviewed_by_user_id' => $admin->getKey(),
                    'reviewed_at' => $refundRequest->reviewed_at ?? now(),
                    'reviewer_note' => str($note ?: 'Full refund reconciled by FinACourt support.')
                        ->limit(500, '')
                        ->toString(),
                    'provider_refund_reference' => $refundRequest->provider_refund_reference ?: $externalReference,
                    'provider_status' => 'externally_confirmed',
                    'requires_review' => false,
                    'failure_code' => null,
                    'failure_message' => null,
                    'failed_at' => null,
                    'completed_at' => now(),
                ]);
                $refundRequest = $refundRequest->refresh();
            }

            return $payment;
        }, 5);

        if ($refundRequest !== null) {
            $this->notifications->statusChanged($refundRequest);
        }

        return $payment;
    }
}
