<?php

namespace App\Http\Controllers\Owner;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateManualPaymentRequest;
use App\Payments\TransitionManualPayment;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function update(
        UpdateManualPaymentRequest $request,
        int $booking,
        TenantContext $context,
        TransitionManualPayment $transition,
    ): RedirectResponse {
        $payment = $transition->handle(
            $booking,
            $context->organization()->getKey(),
            $request->user(),
            PaymentStatus::from($request->validated('status')),
            $request->validated('note'),
        );

        return back()->with('status', "Payment {$payment->reference} is now {$payment->status->label()}.");
    }
}
