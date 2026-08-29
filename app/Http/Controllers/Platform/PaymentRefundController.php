<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\RecordExternalRefund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentRefundController extends Controller
{
    public function __invoke(Request $request, Payment $payment, RecordExternalRefund $refund): RedirectResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $refund->handle(
            $payment,
            $request->user(),
            $validated['external_reference'],
            $validated['note'] ?? null,
        );

        return back()->with('status', 'The external full refund was recorded and the owner earnings were adjusted.');
    }
}
