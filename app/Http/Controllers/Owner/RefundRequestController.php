<?php

namespace App\Http\Controllers\Owner;

use App\Enums\RefundRequestStatus;
use App\Http\Controllers\Controller;
use App\Refunds\ApproveRefundRequest;
use App\Refunds\RejectRefundRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public function approve(
        Request $request,
        int $refundRequest,
        TenantContext $context,
        ApproveRefundRequest $refunds,
    ): RedirectResponse {
        $this->authorizeRefundManagement($request, $context);
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $refundRequest = $refunds->handle(
            $refundRequest,
            $context->organization()->getKey(),
            $request->user(),
            $validated['note'] ?? null,
        );

        return back()->with('status', match ($refundRequest->status) {
            RefundRequestStatus::Processing => 'Refund approved and submitted securely to the payment provider.',
            RefundRequestStatus::Refunded => 'The payment provider confirmed the full refund.',
            RefundRequestStatus::Failed => $refundRequest->requires_review
                ? 'The provider outcome needs platform review before another attempt.'
                : 'The provider rejected the refund. Review the message and retry when ready.',
            default => 'Refund request reviewed.',
        });
    }

    public function reject(
        Request $request,
        int $refundRequest,
        TenantContext $context,
        RejectRefundRequest $refunds,
    ): RedirectResponse {
        $this->authorizeRefundManagement($request, $context);
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $refunds->handle(
            $refundRequest,
            $context->organization()->getKey(),
            $request->user(),
            $validated['note'],
        );

        return back()->with('status', 'Refund request declined. The payment and active booking were left unchanged.');
    }

    private function authorizeRefundManagement(Request $request, TenantContext $context): void
    {
        abort_unless(
            $request->user()->can('manageBookings', $context->organization()),
            403,
        );
    }
}
