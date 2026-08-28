<?php

namespace App\Http\Controllers\Player;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenueReviewRequest;
use App\Models\Booking;
use App\Models\VenueReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VenueReviewController extends Controller
{
    public function store(StoreVenueReviewRequest $request, string $reference): RedirectResponse
    {
        $booking = Booking::query()
            ->where('reference', $reference)
            ->where('player_user_id', $request->user()->getKey())
            ->firstOrFail();

        Gate::authorize('create', [VenueReview::class, $booking]);
        $validated = $request->validated();

        DB::transaction(function () use ($booking, $request, $validated): void {
            $lockedBooking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->review()->exists()) {
                throw ValidationException::withMessages([
                    'review' => 'This booking has already been reviewed.',
                ]);
            }

            VenueReview::query()->create([
                'organization_id' => $lockedBooking->organization_id,
                'venue_id' => $lockedBooking->venue_id,
                'resource_id' => $lockedBooking->resource_id,
                'booking_id' => $lockedBooking->getKey(),
                'player_user_id' => $request->user()->getKey(),
                'rating' => $validated['rating'],
                'body' => $validated['body'] ?? null,
                'status' => ReviewStatus::Pending,
            ]);
        });

        return redirect()->route('player.bookings.show', $booking->reference)
            ->with('status', 'Thank you. Your verified-booking review is awaiting platform moderation.');
    }
}
