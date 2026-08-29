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
            self::OfficialPhoneCall => 'Call to a public venue phone number',
            self::OfficialDomainEmail => 'Reply from an official venue email',
            self::BusinessDocuments => 'Business documents showing you manage it',
            self::InPerson => 'In-person venue check',
            self::LegacyAdminReview => 'Earlier FinACourt check',
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
