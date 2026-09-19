<?php

namespace App\Refunds;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestRefund
{
    public function __construct(
        private readonly RefundNotifier $notifications,
        private readonly PlayerRefundEligibility $eligibility,
    ) {}

    public function handle(Booking $booking, User $player, string $reason): RefundRequest
    {
        $created = false;

        $refundRequest = DB::transaction(function () use ($booking, $player, $reason, &$created): RefundRequest {
            CourtResource::query()->whereKey($booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($booking->player_user_id !== $player->getKey()) {
                abort(403);
            }

            $payment = Payment::query()
                ->where('booking_id', $booking->getKey())
                ->latest('id')
                ->lockForUpdate()
                ->firstOrFail();
            $existing = RefundRequest::query()
                ->where('payment_id', $payment->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($payment->mode !== PaymentMode::HostedCheckout || $payment->status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'refund' => 'Only a completed online payment can be submitted for an automatic refund.',
                ]);
            }

            if (! $this->eligibility->canRequest($booking, $payment)) {
                throw ValidationException::withMessages([
                    'refund' => $this->eligibility->rejectionMessage($booking, $payment),
                ]);
            }

            $created = true;

            return RefundRequest::query()->create([
                'organization_id' => $booking->organization_id,
                'booking_id' => $booking->getKey(),
                'payment_id' => $payment->getKey(),
                'reference' => 'RFD-'.Str::ulid(),
                'status' => RefundRequestStatus::Requested,
                'amount' => $payment->amount,
                'currency' => strtoupper($payment->currency),
                'reason' => $reason,
                'requested_by_user_id' => $player->getKey(),
                'provider' => $payment->provider,
                'provider_payment_reference' => $payment->provider_payment_reference,
                'requested_at' => now(),
            ]);
        }, 5);

        if ($created) {
            $this->notifications->requested($refundRequest);
            $this->notifications->statusChanged($refundRequest);
        }

        return $refundRequest;
    }
}
