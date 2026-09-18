<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Refunds\RequestRefund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RefundRequestController extends Controller
{
    public function store(Request $request, string $reference, RequestRefund $refunds): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $booking = Booking::query()
            ->where('reference', $reference)
            ->where('player_user_id', $request->user()->getKey())
            ->firstOrFail();
        Gate::authorize('viewAsPlayer', $booking);

        $refundRequest = $refunds->handle($booking, $request->user(), $validated['reason']);

        return redirect()->route('player.bookings.show', $booking->reference)
            ->with('status', match ($refundRequest->status->value) {
                'requested' => $refundRequest->court_closure_id !== null
                    ? 'Your booking was cancelled by an emergency closure. The full refund batch is awaiting FinACourt platform approval.'
                    : 'Your full refund request was sent to the venue for review.',
                'processing' => 'Your approved refund is already processing.',
                'refunded' => 'This payment has already been refunded.',
                'rejected' => 'This refund request was already reviewed and declined.',
                default => 'This refund request already exists and needs attention.',
            });
    }
}
