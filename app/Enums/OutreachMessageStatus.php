<?php

namespace App\Enums;

enum OutreachMessageStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Suppressed = 'suppressed';
}
