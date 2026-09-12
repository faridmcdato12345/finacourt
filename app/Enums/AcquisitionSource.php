<?php

namespace App\Enums;

enum AcquisitionSource: string
{
    case MarketplaceOrganic = 'marketplace_organic';
    case MarketplacePromotion = 'marketplace_promotion';
    case CustomerReactivation = 'customer_reactivation';
    case GoogleOrganic = 'google_organic';
    case GoogleMaps = 'google_maps';
    case GoogleAds = 'google_ads';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case QrCode = 'qr_code';
    case SharedLink = 'shared_link';
    case Referral = 'referral';
    case SalesPartner = 'sales_partner';
    case Direct = 'direct';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::MarketplaceOrganic => 'FinACourt search',
            self::MarketplacePromotion => 'FinACourt deal',
            self::CustomerReactivation => 'Message to past players',
            self::GoogleOrganic => 'Google Search',
            self::GoogleMaps => 'Google Maps',
            self::GoogleAds => 'Google Ads',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::QrCode => 'QR code',
            self::SharedLink => 'Shared link',
            self::Referral => 'Referral',
            self::SalesPartner => 'Partner referral',
            self::Direct => 'Direct',
            self::Unknown => 'Unknown',
        };
    }

    public function isGoogle(): bool
    {
        return in_array($this, [self::GoogleOrganic, self::GoogleMaps, self::GoogleAds], true);
    }

    public function canUseTrackedVenueLink(): bool
    {
        return in_array($this, [
            self::GoogleMaps,
            self::GoogleAds,
            self::Facebook,
            self::Instagram,
            self::TikTok,
            self::QrCode,
            self::SharedLink,
        ], true);
    }
}
