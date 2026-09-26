<?php

namespace App\Enums;

enum OutreachMessageType: string
{
    case Initial = 'initial';
    case Followup1 = 'followup_1';
    case Followup2 = 'followup_2';

    public function subject(string $venueName): string
    {
        $subject = "{$venueName} — paano kayo mag-stand out habang dumarami ang courts?";

        return $this === self::Initial ? $subject : "Re: {$subject}";
    }
}
