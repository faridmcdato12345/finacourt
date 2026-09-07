<?php

namespace App\Locations;

use App\Models\PsgcLocation;
use App\Models\Venue;
use Illuminate\Validation\ValidationException;

class ResolveVenueLocation
{
    /** @return array{parent_code: string|null, city_municipality_code: string|null} */
    public function selectionFor(Venue $venue): array
    {
        $parentCode = $venue->psgc_province_code ?: $venue->psgc_region_code;
        $parent = filled($parentCode)
            ? PsgcLocation::query()->find($parentCode)
            : null;

        if ($parent === null && filled($venue->province)) {
            $parent = PsgcLocation::query()
                ->whereIn('level', ['province', 'region', 'area'])
                ->where('name', $venue->province)
                ->orderByRaw("CASE WHEN level = 'province' THEN 0 ELSE 1 END")
                ->first();
        }

        $city = filled($venue->psgc_city_municipality_code)
            ? PsgcLocation::query()->find($venue->psgc_city_municipality_code)
            : null;

        if ($parent !== null && ($city === null || ! $city->isSelectableUnder($parent))) {
            $city = filled($venue->city)
                ? PsgcLocation::query()
                    ->selectableUnder($parent->code)
                    ->where('name', $venue->city)
                    ->first()
                : null;
        }

        if ($parent === null && $city !== null) {
            $derivedParentCode = $city->geographic_parent_code ?: $city->parent_code;
            $parent = filled($derivedParentCode)
                ? PsgcLocation::query()->find($derivedParentCode)
                : null;
        }

        if ($parent === null || $city === null || ! $city->isSelectableUnder($parent)) {
            return [
                'parent_code' => $parent?->code,
                'city_municipality_code' => null,
            ];
        }

        return [
            'parent_code' => $parent->code,
            'city_municipality_code' => $city->code,
        ];
    }

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

        if (! $city->isSelectableUnder($parent)) {
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
