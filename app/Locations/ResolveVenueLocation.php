<?php

namespace App\Locations;

use App\Models\PsgcLocation;
use Illuminate\Validation\ValidationException;

class ResolveVenueLocation
{
    /** @return array{city: string, province: string, psgc_region_code: string, psgc_province_code: string|null, psgc_city_municipality_code: string} */
    public function resolve(string $parentCode, string $cityMunicipalityCode): array
    {
        $parent = PsgcLocation::query()->find($parentCode);
        $city = PsgcLocation::query()->find($cityMunicipalityCode);

        if ($parent === null || ! in_array($parent->level, ['region', 'province', 'area'], true)) {
            throw ValidationException::withMessages([
                'psgc_parent_code' => 'Select a valid Philippine province or region.',
            ]);
        }

        if ($city === null || ! in_array($city->level, ['city', 'municipality'], true)) {
            throw ValidationException::withMessages([
                'psgc_city_municipality_code' => 'Select a valid city or municipality.',
            ]);
        }

        if ($city->parent_code !== $parent->code) {
            throw ValidationException::withMessages([
                'psgc_city_municipality_code' => 'The selected city or municipality does not belong to that province or region.',
            ]);
        }

        $regionCode = $parent->level === 'region' ? $parent->code : $parent->parent_code;

        if ($regionCode === null) {
            throw ValidationException::withMessages([
                'psgc_parent_code' => 'The selected province is missing its PSGC region relationship.',
            ]);
        }

        return [
            'city' => $city->name,
            'province' => $parent->name,
            'psgc_region_code' => $regionCode,
            'psgc_province_code' => $parent->level === 'province' ? $parent->code : null,
            'psgc_city_municipality_code' => $city->code,
        ];
    }
}
