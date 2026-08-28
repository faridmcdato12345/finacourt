<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('manageInventory', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('manageInventory', $organization);
    }

    public function view(User $user, Venue $venue): bool
    {
        return $user->can('manageInventory', $venue->organization);
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->can('manageInventory', $venue->organization);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->can('manageInventory', $venue->organization);
    }
}
