<?php

namespace App\Enums;

enum LeadConflictStatus: string
{
    case Clear = 'clear';
    case Disputed = 'disputed';
    case Resolved = 'resolved';
}
