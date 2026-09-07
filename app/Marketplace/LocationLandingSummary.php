<?php

namespace App\Marketplace;

use App\Enums\ResourceSetting;
use App\Models\CourtResource;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

final class LocationLandingSummary
{
    /**
     * Build crawlable location facts from the exact public inventory already
     * loaded for discovery. Requiring eager-loaded relations makes accidental
     * per-venue or per-court queries fail loudly instead of causing an N+1.
     *
     * @param  Collection<int, Venue>  $venues
     * @return array{
     *     city_name: string,
     *     province: string|null,
     *     place_name: string,
     *     venue_count: int,
     *     court_count: int,
     *     venue_label: string,
     *     court_label: string,
     *     sports: array<int, array{name: string, slug: string, venue_count: int, court_count: int, venue_label: string, court_label: string, minimum_hourly_price: float|null, formatted_minimum_hourly_price: string|null}>,
     *     settings: array<int, array{value: string, label: string}>,
     *     minimum_hourly_price: float|null,
     *     formatted_minimum_hourly_price: string|null,
     *     summary_sports: string|null,
     *     summary_settings: string|null,
     *     introduction: string,
     *     meta_description: string,
     *     inventory_copy: string,
     *     near_me_copy: string,
     *     price_copy: string|null,
     *     setting_copy: string|null
     * }
     */
    public function fromVenues(Collection $venues, string $cityName, ?string $province): array
    {
        $resources = $venues->flatMap(function (Venue $venue): Collection {
            if (! $venue->relationLoaded('resources')) {
                throw new LogicException('Location summaries require eager-loaded venue resources.');
            }

            return $venue->getRelation('resources')->map(function (CourtResource $resource): CourtResource {
                if (! $resource->relationLoaded('sport')) {
                    throw new LogicException('Location summaries require eager-loaded resource sports.');
                }

                return $resource;
            });
        })->unique('id')->values();

        $sports = $resources
            ->groupBy('sport_id')
            ->map(function (Collection $sportResources): ?array {
                /** @var CourtResource|null $firstResource */
                $firstResource = $sportResources->first();
                $sport = $firstResource?->getRelation('sport');

                if ($sport === null) {
                    return null;
                }

                $venueCount = $sportResources->pluck('venue_id')->unique()->count();
                $courtCount = $sportResources->count();
                $minimumRate = $this->minimumRate($sportResources);

                return [
                    'name' => $sport->name,
                    'slug' => $sport->slug,
                    'venue_count' => $venueCount,
                    'court_count' => $courtCount,
                    'venue_label' => $this->countLabel($venueCount, 'venue'),
                    'court_label' => $this->countLabel($courtCount, 'court'),
                    'minimum_hourly_price' => $minimumRate,
                    'formatted_minimum_hourly_price' => $this->formatRate($minimumRate),
                ];
            })
            ->filter()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $settingOrder = collect(ResourceSetting::cases())
            ->mapWithKeys(fn (ResourceSetting $setting, int $index) => [$setting->value => $index]);
        $settings = $resources
            ->map(function (CourtResource $resource): ?array {
                $setting = $resource->setting instanceof ResourceSetting
                    ? $resource->setting
                    : ResourceSetting::tryFrom((string) $resource->setting);

                return $setting === null
                    ? null
                    : ['value' => $setting->value, 'label' => $setting->label()];
            })
            ->filter()
            ->unique('value')
            ->sortBy(fn (array $setting) => $settingOrder->get($setting['value'], PHP_INT_MAX))
            ->values();

        $cityName = Str::squish($cityName);
        $province = filled($province) ? Str::squish((string) $province) : null;
        $placeName = $province !== null && Str::lower($province) !== Str::lower($cityName)
            ? "{$cityName}, {$province}"
            : $cityName;
        $venueCount = $venues->unique('id')->count();
        $courtCount = $resources->count();
        $minimumRate = $this->minimumRate($resources);
        $formattedMinimumRate = $this->formatRate($minimumRate);
        $venueLabel = $this->countLabel($venueCount, 'venue');
        $courtLabel = $this->countLabel($courtCount, 'court');
        $sportNames = $sports->pluck('name');
        $sportList = $this->humanList($sportNames->map(fn (string $name) => Str::lower($name)));
        $settingList = $this->humanList($settings->pluck('label')->map(fn (string $label) => Str::lower($label)));

        $inventoryCopy = "FinACourt currently lists {$this->countLabel($venueCount, 'active sports venue')} in {$placeName}, with {$this->countLabel($courtCount, 'bookable court')}.";

        if ($sportList !== '') {
            $inventoryCopy .= " Current local inventory includes {$sportList}";
            $inventoryCopy .= $formattedMinimumRate !== null
                ? ", with rates starting from ₱{$formattedMinimumRate} per hour."
                : '.';
        }

        $metaSports = $this->humanList($sportNames->take(2)->map(fn (string $name) => Str::lower($name)));
        $metaDescription = "Find sports courts in {$placeName}. Compare {$venueLabel} and {$courtLabel}";
        $metaDescription .= $metaSports !== '' ? ", including {$metaSports}" : '';
        $metaDescription .= $formattedMinimumRate !== null ? ", from ₱{$formattedMinimumRate}/hour" : '';
        $metaDescription .= ' on FinACourt.';

        return [
            'city_name' => $cityName,
            'province' => $province,
            'place_name' => $placeName,
            'venue_count' => $venueCount,
            'court_count' => $courtCount,
            'venue_label' => $venueLabel,
            'court_label' => $courtLabel,
            'sports' => $sports->all(),
            'settings' => $settings->all(),
            'minimum_hourly_price' => $minimumRate,
            'formatted_minimum_hourly_price' => $formattedMinimumRate,
            'summary_sports' => match (true) {
                $sports->isEmpty() => null,
                $sports->count() <= 2 => $sports->pluck('name')->join(' & '),
                default => $this->countLabel($sports->count(), 'sport'),
            },
            'summary_settings' => match (true) {
                $settings->isEmpty() => null,
                $settings->count() <= 2 => $settings->pluck('label')->join(' & '),
                default => $this->countLabel($settings->count(), 'court setting'),
            },
            'introduction' => "Find sports courts in {$placeName}. Compare {$venueLabel}, court settings, hourly rates, and available sports, then view booking options on FinACourt.",
            'meta_description' => Str::limit($metaDescription, 160, ''),
            'inventory_copy' => $inventoryCopy,
            'near_me_copy' => "Looking for a court near you in {$cityName}? Compare local venues, court settings, prices, and booking options.",
            'price_copy' => $formattedMinimumRate !== null
                ? "Court prices in {$cityName} start from ₱{$formattedMinimumRate} per hour."
                : null,
            'setting_copy' => $settingList !== ''
                ? Str::ucfirst("{$settingList} courts are currently listed in {$cityName}.")
                : null,
        ];
    }

    /** @param Collection<int, CourtResource> $resources */
    private function minimumRate(Collection $resources): ?float
    {
        $rates = $resources
            ->map(fn (CourtResource $resource) => $resource->getAttribute('marketplace_unit_price')
                ?? $resource->base_hourly_rate)
            ->filter(fn (mixed $rate) => is_numeric($rate) && (float) $rate > 0)
            ->map(fn (mixed $rate) => (float) $rate);

        return $rates->isEmpty() ? null : (float) $rates->min();
    }

    private function formatRate(?float $rate): ?string
    {
        if ($rate === null) {
            return null;
        }

        return number_format($rate, floor($rate) === $rate ? 0 : 2);
    }

    private function countLabel(int $count, string $noun): string
    {
        return "{$count} ".Str::plural($noun, $count);
    }

    /** @param Collection<int, string> $values */
    private function humanList(Collection $values): string
    {
        return $values->filter()->unique()->values()->join(', ', ' and ');
    }
}
