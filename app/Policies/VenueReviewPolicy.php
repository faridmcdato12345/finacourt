<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\VenueReview;

class VenueReviewPolicy
{
    public function create(User $user, Booking $booking): bool
    {
        return $booking->player_user_id === $user->getKey()
            && $booking->status === BookingStatus::Confirmed
            && $booking->end_at->isPast()
            && ! $booking->review()->exists();
    }

    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function moderate(User $user, VenueReview $review): bool
    {
        return $user->is_platform_admin;
    }
}
