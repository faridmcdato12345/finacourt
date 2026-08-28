<?php

namespace App\Policies;

use App\Models\CourtResource;
use App\Models\User;
use App\Models\Venue;

class CourtResourcePolicy
{
    public function create(User $user, Venue $venue): bool
    {
        return $user->can('manageInventory', $venue->organization);
    }

    public function update(User $user, CourtResource $resource): bool
    {
        return $user->can('manageInventory', $resource->venue->organization);
    }

    public function delete(User $user, CourtResource $resource): bool
    {
        return $user->can('manageInventory', $resource->venue->organization);
    }
}
