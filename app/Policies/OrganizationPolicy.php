<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->is_platform_admin || $this->membership($user, $organization) !== null;
    }

    public function viewDashboard(User $user, Organization $organization): bool
    {
        return $user->is_platform_admin
            || $this->membership($user, $organization)?->hasPermission(OrganizationPermission::ViewDashboard) === true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->is_platform_admin
            || $this->membership($user, $organization)?->hasPermission(OrganizationPermission::ManageOrganization) === true;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->is_platform_admin
            || $this->membership($user, $organization)?->hasPermission(OrganizationPermission::ManageStaff) === true;
    }

    public function manageInventory(User $user, Organization $organization): bool
    {
        return $user->is_platform_admin
            || $this->membership($user, $organization)?->hasPermission(OrganizationPermission::ManageInventory) === true;
    }

    public function manageBookings(User $user, Organization $organization): bool
    {
        return $user->is_platform_admin
            || $this->membership($user, $organization)?->hasPermission(OrganizationPermission::ManageBookings) === true;
    }

    private function membership(User $user, Organization $organization): ?Membership
    {
        return $user->memberships()
            ->where('organization_id', $organization->getKey())
            ->first();
    }
}
