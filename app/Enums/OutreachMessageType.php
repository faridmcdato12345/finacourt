<?php

namespace App\Enums;

enum OutreachMessageType: string
{
    case Initial = 'initial';
    case Followup1 = 'followup_1';
    case Followup2 = 'followup_2';
    case Resend = 'resend';

    public function subject(string $venueName): string
    {
        $subject = "{$venueName} — paano kayo mag-stand out habang dumarami ang courts?";

        return match ($this) {
            self::Initial => $subject,
            self::Resend => "Quick follow-up about {$venueName}",
            default => "Re: {$subject}",
        };
    }
}
