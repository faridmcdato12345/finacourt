<?php

namespace App\Enums;

enum AcquisitionSource: string
{
    case MarketplaceOrganic = 'marketplace_organic';
    case MarketplacePromotion = 'marketplace_promotion';
    case CustomerReactivation = 'customer_reactivation';
    case GoogleOrganic = 'google_organic';
    case GoogleMaps = 'google_maps';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case QrCode = 'qr_code';
    case Referral = 'referral';
    case SalesPartner = 'sales_partner';
    case Direct = 'direct';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::MarketplaceOrganic => 'Marketplace organic',
            self::MarketplacePromotion => 'Marketplace promotion',
            self::CustomerReactivation => 'Customer reactivation',
            self::GoogleOrganic => 'Google organic',
            self::GoogleMaps => 'Google Maps',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::QrCode => 'QR code',
            self::Referral => 'Referral',
            self::SalesPartner => 'Sales partner',
            self::Direct => 'Direct',
            self::Unknown => 'Unknown',
        };
    }

    public function isGoogle(): bool
    {
        return in_array($this, [self::GoogleOrganic, self::GoogleMaps], true);
    }
}
