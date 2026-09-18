<?php

namespace App\CourtClosures;

use App\Enums\CourtClosureBookingStatus;
use App\Enums\CourtClosureRefundStatus;
use App\Enums\RefundRequestStatus;
use App\Models\CourtClosure;
use App\Models\CourtClosureBooking;
use Illuminate\Support\Facades\DB;

class RefreshCourtClosureRefundStatus
{
    public function __construct(private readonly CourtClosureNotifier $notifications) {}

    public function handle(int $closureId): CourtClosure
    {
        [$closure, $changed] = DB::transaction(function () use ($closureId): array {
            $closure = CourtClosure::query()->whereKey($closureId)->lockForUpdate()->firstOrFail();
            $items = $closure->affectedBookings()->with('refundRequest')->get();

            foreach ($items->where('refund_required', true) as $item) {
                $refund = $item->refundRequest;
                $status = match ($refund?->status) {
                    RefundRequestStatus::Processing => CourtClosureBookingStatus::Processing,
                    RefundRequestStatus::Refunded => CourtClosureBookingStatus::Refunded,
                    RefundRequestStatus::Failed, RefundRequestStatus::Rejected => CourtClosureBookingStatus::Failed,
                    default => CourtClosureBookingStatus::AwaitingApproval,
                };

                $item->update([
                    'status' => $status,
                    'failure_message' => $status === CourtClosureBookingStatus::Failed
                        ? ($refund?->failure_message ?? 'The refund could not be processed.')
                        : null,
                ]);
            }

            $refundItems = $items->where('refund_required', true);
            $status = match (true) {
                $refundItems->isEmpty() => CourtClosureRefundStatus::NotRequired,
                $refundItems->every(fn (CourtClosureBooking $item) => $item->status === CourtClosureBookingStatus::Refunded) => CourtClosureRefundStatus::Completed,
                $refundItems->contains(fn (CourtClosureBooking $item) => $item->status === CourtClosureBookingStatus::Failed) => CourtClosureRefundStatus::Attention,
                $closure->approved_at !== null || $refundItems->contains(fn (CourtClosureBooking $item) => $item->status === CourtClosureBookingStatus::Processing) => CourtClosureRefundStatus::Processing,
                default => CourtClosureRefundStatus::AwaitingApproval,
            };
            $changed = $closure->refund_status !== $status;

            $closure->update([
                'refund_status' => $status,
                'completed_at' => in_array($status, [CourtClosureRefundStatus::NotRequired, CourtClosureRefundStatus::Completed], true)
                    ? ($closure->completed_at ?? now())
                    : null,
            ]);

            return [$closure->refresh(), $changed];
        }, 5);

        if ($changed) {
            $this->notifications->outcome($closure);
        }

        return $closure;
    }
}
