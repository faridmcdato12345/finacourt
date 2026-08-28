<?php

namespace App\Enums;

enum DirectorySourceType: string
{
    case OfficialWebsite = 'official_website';
    case GovernmentRegistry = 'government_registry';
    case OwnerSubmission = 'owner_submission';
    case PublicSignage = 'public_signage';
    case LicensedDataset = 'licensed_dataset';
    case OtherPublicSource = 'other_public_source';

    public function label(): string
    {
        return match ($this) {
            self::OfficialWebsite => 'Official venue website',
            self::GovernmentRegistry => 'Government or public record',
            self::OwnerSubmission => 'Venue owner or manager',
            self::PublicSignage => 'Venue sign or in-person check',
            self::LicensedDataset => 'Licensed information provider',
            self::OtherPublicSource => 'Another trusted public source',
        };
    }
}
