<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case ViewDashboard = 'dashboard.view';
    case ManageOrganization = 'organization.manage';
    case ManageStaff = 'staff.manage';
    case ManageInventory = 'inventory.manage';
    case ManageBookings = 'bookings.manage';

    /** @return array<int, self> */
    public static function assignableToStaff(): array
    {
        return [
            self::ManageBookings,
            self::ManageInventory,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewDashboard => 'Dashboard access',
            self::ManageOrganization => 'Business settings',
            self::ManageStaff => 'Team management',
            self::ManageInventory => 'Venues and courts',
            self::ManageBookings => 'Bookings and schedules',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ViewDashboard => 'Sign in to the organization workspace and view its overview.',
            self::ManageOrganization => 'Change organization-level settings.',
            self::ManageStaff => 'Invite, suspend, and remove staff.',
            self::ManageInventory => 'Manage venues, courts, hours, pricing, promotions, and visibility.',
            self::ManageBookings => 'Manage bookings, court blocks, emergency closures, and customers.',
        };
    }
}
