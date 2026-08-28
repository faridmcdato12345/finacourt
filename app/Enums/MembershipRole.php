<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
