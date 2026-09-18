<?php

namespace App\CourtClosures;

use App\Enums\CourtClosureBookingStatus;
use App\Enums\CourtClosureRefundStatus;
use App\Enums\MembershipRole;
use App\Models\CourtClosure;
use App\Models\User;
use App\Notifications\CourtClosureNotification;
use Illuminate\Support\Facades\Notification;

class CourtClosureNotifier
{
    public function created(CourtClosure $closure): void
    {
        $closure->loadMissing([
            'organization:id,name',
            'affectedBookings.booking.player:id,name,email',
            'affectedBookings.booking.venue:id,name',
            'affectedBookings.booking.resource:id,name',
        ]);

        foreach ($closure->affectedBookings as $item) {
            if ($item->notification_queued_at !== null) {
                continue;
            }

            $booking = $item->booking;
            $start = $booking->start_at->setTimezone($booking->timezone);
            $refundMessage = match ($item->status) {
                CourtClosureBookingStatus::AwaitingApproval => ' Its full online refund is awaiting FinACourt platform approval.',
                CourtClosureBookingStatus::Processing => ' Its full online refund is already processing.',
                CourtClosureBookingStatus::Refunded => ' Its full online refund has been completed.',
                CourtClosureBookingStatus::ManualRefundRequired => ' The venue will contact you about returning the payment collected at the court.',
                CourtClosureBookingStatus::Failed => ' FinACourt support is reviewing the online refund.',
                default => '',
            };
            $notification = new CourtClosureNotification(
                kind: 'booking_cancelled_emergency',
                title: 'Booking cancelled because the court closed',
                message: "Your {$booking->resource->name} booking at {$booking->venue->name} on {$start->format('M j, Y')} at {$start->format('g:i A')} was cancelled because {$closure->reason}.{$refundMessage}",
                closureReference: $closure->reference,
                url: $booking->player !== null ? route('player.bookings.show', $booking->reference) : '',
                actionLabel: 'View booking and refund status',
            );

            if ($booking->player !== null) {
                $booking->player->notify($notification);
            } elseif ($booking->customer_email !== null) {
                Notification::route('mail', $booking->customer_email)->notify($notification);
            }

            $item->forceFill(['notification_queued_at' => now()])->saveQuietly();
        }

        if ($closure->refund_status === CourtClosureRefundStatus::Attention) {
            $this->outcome($closure);

            return;
        }

        if ($closure->refund_status !== CourtClosureRefundStatus::AwaitingApproval) {
            return;
        }

        $administrators = User::query()->where('is_platform_admin', true)->get();
        if ($administrators->isEmpty()) {
            return;
        }

        $count = $closure->affectedBookings->where('refund_required', true)->count();
        Notification::send($administrators, new CourtClosureNotification(
            kind: 'closure_refunds_awaiting_approval',
            title: 'Emergency closure refunds need approval',
            message: "{$closure->organization->name} cancelled {$count} paid online booking(s). Review and approve the full-refund batch before FinACourt submits it to the payment provider.",
            closureReference: $closure->reference,
            url: route('platform.court-closures.index').'#closure-'.$closure->getKey(),
            actionLabel: 'Review refund batch',
        ));
    }

    public function outcome(CourtClosure $closure): void
    {
        $closure->loadMissing('organization:id,name');
        $status = $closure->refund_status;

        if (! in_array($status, [CourtClosureRefundStatus::Completed, CourtClosureRefundStatus::Attention], true)) {
            return;
        }

        $isComplete = $status === CourtClosureRefundStatus::Completed;
        $owners = User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('organization_id', $closure->organization_id)
                ->where('role', MembershipRole::Owner))
            ->get();

        Notification::send($owners, new CourtClosureNotification(
            kind: $isComplete ? 'closure_refunds_completed' : 'closure_refunds_attention',
            title: $isComplete ? 'Emergency closure refunds completed' : 'Emergency closure refunds need attention',
            message: $isComplete
                ? "All automatic refunds for {$closure->reference} were confirmed by the payment provider."
                : "One or more automatic refunds for {$closure->reference} need FinACourt platform review.",
            closureReference: $closure->reference,
            url: route('owner.court-closures.index').'#closure-'.$closure->getKey(),
            actionLabel: 'View closure',
        ));

        if (! $isComplete) {
            $administrators = User::query()->where('is_platform_admin', true)->get();
            Notification::send($administrators, new CourtClosureNotification(
                kind: 'closure_refunds_attention',
                title: 'Emergency closure refunds need attention',
                message: "One or more automatic refunds for {$closure->organization->name} need reconciliation or a retry.",
                closureReference: $closure->reference,
                url: route('platform.court-closures.index').'#closure-'.$closure->getKey(),
                actionLabel: 'Review refund batch',
            ));
        }
    }
}
