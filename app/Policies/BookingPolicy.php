<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Organization;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('manageBookings', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('manageBookings', $organization);
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can('manageBookings', $booking->organization);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->can('manageBookings', $booking->organization);
    }

    public function viewAsPlayer(User $user, Booking $booking): bool
    {
        return $booking->player_user_id === $user->getKey();
    }

    public function cancelAsPlayer(User $user, Booking $booking): bool
    {
        return $this->viewAsPlayer($user, $booking)
            && $booking->start_at->isFuture();
    }
}
