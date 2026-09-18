<?php

namespace App\Refunds;

use App\Enums\MembershipRole;
use App\Enums\RefundRequestStatus;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\RefundNotification;
use Illuminate\Support\Facades\Notification;

class RefundNotifier
{
    public function requested(RefundRequest $refundRequest): void
    {
        $refundRequest->loadMissing(['booking.venue', 'requestedBy']);
        $booking = $refundRequest->booking;
        $owners = User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('organization_id', $refundRequest->organization_id)
                ->where('role', MembershipRole::Owner))
            ->get();

        if ($owners->isEmpty()) {
            return;
        }

        $date = $booking->start_at->setTimezone($booking->timezone)->toDateString();
        Notification::send($owners, new RefundNotification(
            kind: 'refund_requested',
            title: 'Player requested a refund',
            message: "{$booking->customer_name} requested a full {$refundRequest->currency} {$refundRequest->amount} refund for {$booking->venue->name}.",
            refundReference: $refundRequest->reference,
            url: route('owner.bookings.index', ['date' => $date]).'#refund-request-'.$refundRequest->getKey(),
            actionLabel: 'Review refund request',
        ));
    }

    public function statusChanged(RefundRequest $refundRequest): void
    {
        $refundRequest->loadMissing(['booking.player', 'booking.venue']);
        $player = $refundRequest->booking->player;

        if ($player === null) {
            return;
        }

        [$title, $message] = match ($refundRequest->status) {
            RefundRequestStatus::Requested => [
                'Refund request submitted',
                $refundRequest->court_closure_id !== null
                    ? 'Your booking was cancelled by an emergency closure. FinACourt will review the automatic full refund batch.'
                    : 'The venue will review your full refund request before any money is returned.',
            ],
            RefundRequestStatus::Processing => [
                'Refund approved and processing',
                "Your {$refundRequest->currency} {$refundRequest->amount} refund was sent securely to the payment provider.",
            ],
            RefundRequestStatus::Refunded => [
                'Refund completed',
                "The payment provider confirmed your full {$refundRequest->currency} {$refundRequest->amount} refund. Posting time depends on your original payment method.",
            ],
            RefundRequestStatus::Rejected => [
                'Refund request declined',
                $refundRequest->reviewer_note ?: 'The venue declined this refund request. Contact the venue if you need clarification.',
            ],
            RefundRequestStatus::Failed => [
                'Refund needs attention',
                $refundRequest->requires_review
                    ? 'The automatic refund could not be confirmed. FinACourt support must reconcile it before another attempt.'
                    : ($refundRequest->court_closure_id !== null
                        ? 'The payment provider could not process the refund. FinACourt will review and retry it.'
                        : 'The payment provider could not process the refund. The venue can review and retry it.'),
            ],
        };

        $player->notify(new RefundNotification(
            kind: 'refund_'.$refundRequest->status->value,
            title: $title,
            message: $message,
            refundReference: $refundRequest->reference,
            url: route('player.bookings.show', $refundRequest->booking->reference),
            actionLabel: 'View booking',
        ));
    }

    public function platformReviewRequired(RefundRequest $refundRequest): void
    {
        $administrators = User::query()->where('is_platform_admin', true)->get();

        if ($administrators->isEmpty()) {
            return;
        }

        Notification::send($administrators, new RefundNotification(
            kind: 'refund_review_required',
            title: 'Refund requires platform review',
            message: $refundRequest->failure_message ?: 'An automatic refund has an unknown provider outcome.',
            refundReference: $refundRequest->reference,
            url: route('platform.payments.index'),
            actionLabel: 'Review payments',
        ));
    }
}
