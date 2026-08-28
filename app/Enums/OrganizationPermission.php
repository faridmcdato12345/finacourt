<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case ViewDashboard = 'dashboard.view';
    case ManageOrganization = 'organization.manage';
    case ManageStaff = 'staff.manage';
    case ManageInventory = 'inventory.manage';
    case ManageBookings = 'bookings.manage';
}
