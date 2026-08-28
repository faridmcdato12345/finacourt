<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('manageInventory', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('manageInventory', $organization);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can('manageInventory', $promotion->organization);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can('manageInventory', $promotion->organization);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can('manageInventory', $promotion->organization);
    }
}
