<?php

namespace App\CourtClosures;

use App\Enums\BookingStatus;
use App\Enums\CourtClosureBookingStatus;
use App\Enums\CourtClosureRefundStatus;
use App\Enums\CourtClosureStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtClosure;
use App\Models\CourtClosureBooking;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCourtClosure
{
    public function __construct(
        private readonly CourtClosureImpact $impact,
        private readonly CourtClosureConfirmation $confirmation,
        private readonly CourtClosureNotifier $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Organization $organization, User $actor, array $data): CourtClosure
    {
        $closure = DB::transaction(function () use ($organization, $actor, $data): CourtClosure {
            $impact = $this->impact->inspect($organization, $data, lock: true);
            if (! $this->confirmation->valid(
                $data['confirmation_token'] ?? null,
                $organization,
                $actor,
                $data,
                $impact,
            )) {
                throw ValidationException::withMessages([
                    'confirmed' => 'The closure impact changed or the preview expired. Preview the affected bookings again.',
                ]);
            }

            $reference = 'CLS-'.Str::ulid();
            $now = now('UTC');
            $closure = CourtClosure::query()->create([
                'organization_id' => $organization->getKey(),
                'venue_id' => $impact['venue_id'],
                'reference' => $reference,
                'scope' => $data['scope'],
                'starts_at' => $impact['starts_at'],
                'ends_at' => $impact['ends_at'],
                'timezone' => $organization->timezone,
                'reason' => $data['reason'],
                'status' => CourtClosureStatus::Active,
                'refund_status' => CourtClosureRefundStatus::NotRequired,
                'created_by_user_id' => $actor->getKey(),
            ]);

            $closure->resources()->attach($impact['resources']->modelKeys());

            foreach ($impact['resources'] as $resource) {
                CourtAvailabilityBlock::query()->create([
                    'organization_id' => $organization->getKey(),
                    'venue_id' => $resource->venue_id,
                    'resource_id' => $resource->getKey(),
                    'court_closure_id' => $closure->getKey(),
                    'starts_at' => $impact['starts_at'],
                    'ends_at' => $impact['ends_at'],
                    'timezone' => $organization->timezone,
                    'is_all_day' => false,
                    'reason' => Str::limit("Emergency closure {$reference}: {$data['reason']}", 500, ''),
                    'created_by_user_id' => $actor->getKey(),
                ]);
            }

            foreach ($impact['bookings'] as $booking) {
                $refundRequest = null;
                $payment = $booking->payment === null
                    ? null
                    : Payment::query()->whereKey($booking->payment->getKey())->lockForUpdate()->firstOrFail();
                $paymentStatus = $payment?->status;
                $paymentMode = $payment?->mode;
                $refundRequired = $payment !== null
                    && $paymentMode === PaymentMode::HostedCheckout
                    && $paymentStatus === PaymentStatus::Paid;
                $itemStatus = $refundRequired
                    ? CourtClosureBookingStatus::AwaitingApproval
                    : ($paymentMode === PaymentMode::PayAtVenue && $paymentStatus === PaymentStatus::Paid
                        ? CourtClosureBookingStatus::ManualRefundRequired
                        : CourtClosureBookingStatus::Cancelled);

                $this->cancelBooking($booking, $payment, $actor, $closure);

                if ($refundRequired) {
                    $refundRequest = $this->refundRequest($closure, $booking, $payment, $actor);
                    $itemStatus = match ($refundRequest->status) {
                        RefundRequestStatus::Processing => CourtClosureBookingStatus::Processing,
                        RefundRequestStatus::Refunded => CourtClosureBookingStatus::Refunded,
                        RefundRequestStatus::Failed => CourtClosureBookingStatus::Failed,
                        default => CourtClosureBookingStatus::AwaitingApproval,
                    };
                }

                CourtClosureBooking::query()->create([
                    'court_closure_id' => $closure->getKey(),
                    'booking_id' => $booking->getKey(),
                    'payment_id' => $payment?->getKey(),
                    'status' => $itemStatus,
                    'refund_required' => $refundRequired,
                    'payment_mode' => $paymentMode,
                    'payment_status_at_closure' => $paymentStatus,
                    'refund_amount' => $paymentStatus === PaymentStatus::Paid ? $payment->amount : '0.00',
                    'currency' => $payment?->currency ?? $booking->currency,
                    'failure_message' => isset($refundRequest) && $refundRequest->status === RefundRequestStatus::Failed
                        ? $refundRequest->failure_message
                        : null,
                    'cancelled_at' => $now,
                ]);
            }

            $refundItems = $closure->affectedBookings()->where('refund_required', true)->get();
            $refundStatus = match (true) {
                $refundItems->isEmpty() => CourtClosureRefundStatus::NotRequired,
                $refundItems->every(fn (CourtClosureBooking $item) => $item->status === CourtClosureBookingStatus::Refunded) => CourtClosureRefundStatus::Completed,
                $refundItems->contains(fn (CourtClosureBooking $item) => $item->status === CourtClosureBookingStatus::Failed) => CourtClosureRefundStatus::Attention,
                $refundItems->contains(fn (CourtClosureBooking $item) => $item->status === CourtClosureBookingStatus::Processing) => CourtClosureRefundStatus::Processing,
                default => CourtClosureRefundStatus::AwaitingApproval,
            };

            $closure->update([
                'refund_status' => $refundStatus,
                'completed_at' => in_array($refundStatus, [CourtClosureRefundStatus::NotRequired, CourtClosureRefundStatus::Completed], true)
                    ? $now
                    : null,
            ]);

            return $closure->refresh();
        }, 5);

        $closure->load([
            'organization:id,name',
            'resources:id,name,venue_id',
            'affectedBookings.booking.player:id,name,email',
            'affectedBookings.booking.venue:id,name,slug',
            'affectedBookings.booking.resource:id,name',
        ]);
        $this->notifications->created($closure);

        return $closure;
    }

    private function cancelBooking(Booking $booking, ?Payment $payment, User $actor, CourtClosure $closure): void
    {
        $booking->update([
            'status' => BookingStatus::Cancelled,
            'expires_at' => null,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->getKey(),
            'cancellation_reason' => Str::limit("Venue emergency closure {$closure->reference}: {$closure->reason}", 500, ''),
        ]);

        if ($payment?->status !== PaymentStatus::Pending) {
            return;
        }

        $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
        $payment->update([
            'status' => PaymentStatus::Cancelled,
            'cancelled_at' => now(),
            'verified_by_user_id' => $actor->getKey(),
        ]);
        $payment->transitions()->create([
            'from_status' => PaymentStatus::Pending,
            'to_status' => PaymentStatus::Cancelled,
            'source' => 'court_closure',
            'actor_user_id' => $actor->getKey(),
            'note' => "Payment cancelled by emergency closure {$closure->reference}.",
            'metadata' => ['court_closure_id' => $closure->getKey()],
        ]);
        $booking->update(['payment_status' => PaymentStatus::Cancelled]);
    }

    private function refundRequest(CourtClosure $closure, Booking $booking, Payment $payment, User $actor): RefundRequest
    {
        $refundRequest = RefundRequest::query()
            ->where('payment_id', $payment->getKey())
            ->lockForUpdate()
            ->first();

        if ($refundRequest === null) {
            return RefundRequest::query()->create([
                'organization_id' => $closure->organization_id,
                'court_closure_id' => $closure->getKey(),
                'booking_id' => $booking->getKey(),
                'payment_id' => $payment->getKey(),
                'reference' => 'RFD-'.Str::ulid(),
                'status' => RefundRequestStatus::Requested,
                'amount' => $payment->amount,
                'currency' => strtoupper($payment->currency),
                'reason' => Str::limit("Owner emergency closure {$closure->reference}: {$closure->reason}", 500, ''),
                'requested_by_user_id' => $actor->getKey(),
                'provider' => $payment->provider,
                'provider_payment_reference' => $payment->provider_payment_reference,
                'requested_at' => now(),
            ]);
        }

        $attributes = ['court_closure_id' => $closure->getKey()];

        if ($refundRequest->status === RefundRequestStatus::Rejected
            || ($refundRequest->status === RefundRequestStatus::Failed && ! $refundRequest->requires_review)) {
            $attributes = [
                ...$attributes,
                'status' => RefundRequestStatus::Requested,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'reviewer_note' => null,
                'failure_code' => null,
                'failure_message' => null,
                'failed_at' => null,
                'requires_review' => false,
            ];
        }

        $refundRequest->update($attributes);

        return $refundRequest->refresh();
    }
}
