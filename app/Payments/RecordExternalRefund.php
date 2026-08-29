<?php

namespace App\Payments;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordExternalRefund
{
    public function __construct(private readonly ApplyPaymentTransition $transitions) {}

    public function handle(Payment $payment, User $admin, string $externalReference, ?string $note = null): Payment
    {
        abort_unless($admin->is_platform_admin, 403);

        return DB::transaction(function () use ($payment, $admin, $externalReference, $note): Payment {
            $booking = $payment->booking()->firstOrFail();
            CourtResource::query()->whereKey($booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment)->lockForUpdate()->firstOrFail();

            if ($payment->mode !== PaymentMode::HostedCheckout || $payment->status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'payment' => 'Only a paid online checkout can be recorded as externally refunded.',
                ]);
            }

            return $this->transitions->handleLocked(
                $payment,
                $booking,
                PaymentStatus::Refunded,
                'platform_external_refund',
                $admin,
                note: $note ?: 'Full refund completed outside FinACourt.',
                metadata: ['external_refund_reference' => $externalReference],
            );
        }, 5);
    }
}
