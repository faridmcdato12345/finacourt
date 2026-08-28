<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Enums\OrganizationPermission;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Membership> */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => MembershipRole::Staff,
            'permissions' => [OrganizationPermission::ViewDashboard->value],
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'role' => MembershipRole::Owner,
            'permissions' => null,
        ]);
    }

    /** @param array<int, OrganizationPermission|string> $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn () => [
            'role' => MembershipRole::Staff,
            'permissions' => array_map(
                fn ($permission) => $permission instanceof OrganizationPermission ? $permission->value : $permission,
                $permissions,
            ),
        ]);
    }
}
