<?php

namespace App\Enums;

enum OutreachLeadStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Completed = 'completed';
    case Replied = 'replied';
    case Claimed = 'claimed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
}
