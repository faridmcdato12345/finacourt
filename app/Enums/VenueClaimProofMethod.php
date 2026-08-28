<?php

namespace App\Enums;

enum VenueClaimProofMethod: string
{
    case PublicEmailCode = 'public_email_code';
    case OfficialPhoneCall = 'official_phone_call';
    case OfficialDomainEmail = 'official_domain_email';
    case BusinessDocuments = 'business_documents';
    case InPerson = 'in_person';
    case LegacyAdminReview = 'legacy_admin_review';

    public function label(): string
    {
        return match ($this) {
            self::PublicEmailCode => 'Code sent to the venue’s public email',
            self::OfficialPhoneCall => 'Call to the independently sourced venue number',
            self::OfficialDomainEmail => 'Reply from an official venue-domain email',
            self::BusinessDocuments => 'Business and venue-control documents',
            self::InPerson => 'In-person venue verification',
            self::LegacyAdminReview => 'Earlier administrator review',
        };
    }

    public function isManualReview(): bool
    {
        return in_array($this, [
            self::OfficialPhoneCall,
            self::OfficialDomainEmail,
            self::BusinessDocuments,
            self::InPerson,
        ], true);
    }
}
