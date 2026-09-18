<?php

namespace App\Jobs;

use App\CourtClosures\RefreshCourtClosureRefundStatus;
use App\Enums\CourtClosureBookingStatus;
use App\Enums\RefundRequestStatus;
use App\Models\CourtClosureBooking;
use App\Models\User;
use App\Refunds\ApproveRefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SubmitCourtClosureRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $closureBookingId,
        public readonly int $actorUserId,
    ) {}

    public function handle(
        ApproveRefundRequest $approve,
        RefreshCourtClosureRefundStatus $refresh,
    ): void {
        $item = CourtClosureBooking::query()
            ->with(['closure', 'refundRequest'])
            ->findOrFail($this->closureBookingId);

        if (! $item->refund_required || $item->status === CourtClosureBookingStatus::Refunded) {
            return;
        }

        $refund = $item->refundRequest;
        if ($refund === null) {
            throw new \RuntimeException('The closure refund request is missing.');
        }

        $approve->handle(
            $refund->getKey(),
            $item->closure->organization_id,
            User::query()->findOrFail($this->actorUserId),
            "Approved as part of emergency closure {$item->closure->reference}.",
        );
        $refresh->handle($item->court_closure_id);
    }

    public function failed(?Throwable $exception): void
    {
        $item = CourtClosureBooking::query()->find($this->closureBookingId);
        if ($item === null || $item->status === CourtClosureBookingStatus::Refunded) {
            return;
        }

        $item->update([
            'status' => CourtClosureBookingStatus::Failed,
            'failure_message' => 'The queued refund could not be submitted after multiple attempts.',
        ]);
        $item->refundRequest()->update([
            'status' => RefundRequestStatus::Failed,
            'failure_code' => 'queue_attempts_exhausted',
            'failure_message' => 'The queued refund could not be submitted after multiple attempts.',
            'failed_at' => now(),
            'requires_review' => false,
        ]);
        app(RefreshCourtClosureRefundStatus::class)->handle($item->court_closure_id);
    }
}
