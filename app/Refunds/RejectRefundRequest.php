<?php

namespace App\Refunds;

use App\Enums\RefundRequestStatus;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectRefundRequest
{
    public function __construct(private readonly RefundNotifier $notifications) {}

    public function handle(int $refundRequestId, int $organizationId, User $actor, string $note): RefundRequest
    {
        $refundRequest = DB::transaction(function () use ($refundRequestId, $organizationId, $actor, $note): RefundRequest {
            $refundRequest = RefundRequest::query()
                ->whereKey($refundRequestId)
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($refundRequest->status !== RefundRequestStatus::Requested) {
                throw ValidationException::withMessages([
                    'refund' => 'Only a pending refund request can be declined.',
                ]);
            }

            $refundRequest->update([
                'status' => RefundRequestStatus::Rejected,
                'reviewed_by_user_id' => $actor->getKey(),
                'reviewed_at' => now(),
                'reviewer_note' => $note,
            ]);

            return $refundRequest->refresh();
        }, 5);

        $this->notifications->statusChanged($refundRequest);

        return $refundRequest;
    }
}
