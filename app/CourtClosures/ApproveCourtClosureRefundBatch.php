<?php

namespace App\CourtClosures;

use App\Enums\CourtClosureBookingStatus;
use App\Enums\CourtClosureRefundStatus;
use App\Enums\RefundRequestStatus;
use App\Jobs\SubmitCourtClosureRefund;
use App\Models\CourtClosure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveCourtClosureRefundBatch
{
    public function handle(int $closureId, User $actor): CourtClosure
    {
        if (! $actor->is_platform_admin) {
            abort(403);
        }

        [$closure, $itemIds] = DB::transaction(function () use ($closureId, $actor): array {
            $closure = CourtClosure::query()->whereKey($closureId)->lockForUpdate()->firstOrFail();

            if (! in_array($closure->refund_status, [CourtClosureRefundStatus::AwaitingApproval, CourtClosureRefundStatus::Attention], true)) {
                throw ValidationException::withMessages([
                    'refunds' => 'This refund batch is not awaiting approval or retry.',
                ]);
            }

            $items = $closure->affectedBookings()
                ->where('refund_required', true)
                ->with('refundRequest')
                ->lockForUpdate()
                ->get();
            $eligible = $items->filter(function ($item): bool {
                $refund = $item->refundRequest;

                return $refund !== null
                    && ($refund->status === RefundRequestStatus::Requested
                        || ($refund->status === RefundRequestStatus::Failed && ! $refund->requires_review));
            });

            if ($eligible->isEmpty()) {
                throw ValidationException::withMessages([
                    'refunds' => 'No refunds in this batch can be submitted automatically. Review the flagged provider outcomes first.',
                ]);
            }

            $closure->update([
                'refund_status' => CourtClosureRefundStatus::Processing,
                'approved_by_user_id' => $actor->getKey(),
                'approved_at' => $closure->approved_at ?? now(),
                'completed_at' => null,
            ]);

            $eligible->each(fn ($item) => $item->update([
                'status' => CourtClosureBookingStatus::Processing,
                'failure_message' => null,
            ]));

            return [$closure->refresh(), $eligible->modelKeys()];
        }, 5);

        foreach ($itemIds as $itemId) {
            SubmitCourtClosureRefund::dispatch($itemId, $actor->getKey());
        }

        return $closure;
    }
}
