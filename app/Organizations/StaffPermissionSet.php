<?php

namespace App\Organizations;

use App\Enums\OrganizationPermission;

class StaffPermissionSet
{
    /** @return array<int, string> */
    public function allowedValues(): array
    {
        return array_map(
            fn (OrganizationPermission $permission): string => $permission->value,
            OrganizationPermission::assignableToStaff(),
        );
    }

    /** @param array<int, mixed> $permissions
     * @return array<int, string>
     */
    public function normalize(array $permissions): array
    {
        $allowed = $this->allowedValues();
        $selected = array_values(array_unique(array_filter(
            $permissions,
            fn (mixed $permission): bool => is_string($permission) && in_array($permission, $allowed, true),
        )));

        return [OrganizationPermission::ViewDashboard->value, ...$selected];
    }

    /** @return array<int, array{value: string, label: string, description: string}> */
    public function options(): array
    {
        return array_map(
            fn (OrganizationPermission $permission): array => [
                'value' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
            ],
            OrganizationPermission::assignableToStaff(),
        );
    }
}
