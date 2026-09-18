<?php

namespace App\Http\Controllers\Platform;

use App\CourtClosures\ApproveCourtClosureRefundBatch;
use App\Enums\CourtClosureBookingStatus;
use App\Enums\CourtClosureRefundStatus;
use App\Http\Controllers\Controller;
use App\Models\CourtClosure;
use App\Models\CourtClosureBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourtClosureController extends Controller
{
    public function index(): Response
    {
        $closures = CourtClosure::query()
            ->with([
                'organization:id,name',
                'venue:id,name',
                'resources:id,name',
                'createdBy:id,name',
                'approvedBy:id,name',
                'affectedBookings.booking:id,reference,customer_name,start_at,timezone,venue_id,resource_id',
                'affectedBookings.booking.venue:id,name',
                'affectedBookings.booking.resource:id,name',
                'affectedBookings.refundRequest',
            ])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (CourtClosure $closure) => $this->payload($closure));

        return Inertia::render('Platform/CourtClosures/Index', ['closures' => $closures]);
    }

    public function approve(
        Request $request,
        int $closure,
        ApproveCourtClosureRefundBatch $approve,
    ): RedirectResponse {
        $approved = $approve->handle($closure, $request->user());

        return back()->with('status', "Refund batch {$approved->reference} approved. Individual refunds were queued for secure provider submission.");
    }

    public function retry(
        Request $request,
        int $closure,
        ApproveCourtClosureRefundBatch $approve,
    ): RedirectResponse {
        $retried = $approve->handle($closure, $request->user());

        return back()->with('status', "Eligible failed refunds in {$retried->reference} were queued again.");
    }

    /** @return array<string, mixed> */
    private function payload(CourtClosure $closure): array
    {
        $items = $closure->affectedBookings;

        return [
            'id' => $closure->getKey(),
            'reference' => $closure->reference,
            'organization' => $closure->organization->name,
            'venue' => $closure->venue?->name,
            'courts' => $closure->resources->pluck('name')->all(),
            'reason' => $closure->reason,
            'status_label' => $closure->status->label(),
            'refund_status' => $closure->refund_status->value,
            'refund_status_label' => $closure->refund_status->label(),
            'starts_at' => $closure->starts_at->setTimezone($closure->timezone)->format('M j, Y g:i A'),
            'ends_at' => $closure->ends_at?->setTimezone($closure->timezone)->format('M j, Y g:i A'),
            'created_by' => $closure->createdBy?->name,
            'approved_by' => $closure->approvedBy?->name,
            'booking_count' => $items->count(),
            'refund_count' => $items->where('refund_required', true)->count(),
            'refund_total' => number_format((float) $items->where('refund_required', true)->sum(fn (CourtClosureBooking $item) => (float) $item->refund_amount), 2, '.', ''),
            'can_approve' => $closure->refund_status === CourtClosureRefundStatus::AwaitingApproval,
            'can_retry' => $closure->refund_status === CourtClosureRefundStatus::Attention
                && $items->contains(fn (CourtClosureBooking $item) => $item->status === CourtClosureBookingStatus::Failed
                    && $item->refundRequest !== null
                    && ! $item->refundRequest->requires_review),
            'bookings' => $items->map(function (CourtClosureBooking $item): array {
                $booking = $item->booking;

                return [
                    'reference' => $booking->reference,
                    'customer_name' => $booking->customer_name,
                    'venue' => $booking->venue->name,
                    'resource' => $booking->resource->name,
                    'start' => $booking->start_at->setTimezone($booking->timezone)->format('M j, Y g:i A'),
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'amount' => $item->refund_amount,
                    'refund_reference' => $item->refundRequest?->reference,
                    'failure_message' => $item->failure_message,
                    'requires_review' => $item->refundRequest?->requires_review ?? false,
                ];
            }),
        ];
    }
}
