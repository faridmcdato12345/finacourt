<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\ReactivationCampaign;
use App\Models\User;

class ReactivationCampaignPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('manageBookings', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('manageBookings', $organization);
    }

    public function view(User $user, ReactivationCampaign $campaign): bool
    {
        return $user->can('manageBookings', $campaign->organization);
    }

    public function send(User $user, ReactivationCampaign $campaign): bool
    {
        return $user->can('manageBookings', $campaign->organization);
    }

    public function cancel(User $user, ReactivationCampaign $campaign): bool
    {
        return $user->can('manageBookings', $campaign->organization);
    }
}
