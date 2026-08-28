<?php

namespace App\Marketplace;

use App\Models\Venue;

class StructuredData
{
    /** @param array<int, array{name: string, url: string}> $items
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function venue(Venue $venue): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsActivityLocation',
            '@id' => route('marketplace.venues.show', $venue->slug).'#venue',
            'name' => $venue->name,
            'url' => route('marketplace.venues.show', $venue->slug),
            'description' => $venue->description,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $venue->address,
                'addressLocality' => $venue->city,
                'addressRegion' => $venue->province,
            ],
            'telephone' => $venue->phone,
            'email' => $venue->email,
            'openingHoursSpecification' => $venue->operatingHours
                ->reject(fn ($hours) => $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at)
                ->map(fn ($hours) => [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'https://schema.org/'.$hours->day_of_week->label(),
                    'opens' => substr($hours->opens_at, 0, 5),
                    'closes' => substr($hours->closes_at, 0, 5),
                ])->values()->all(),
            'amenityFeature' => $venue->amenities->map(fn ($amenity) => [
                '@type' => 'LocationFeatureSpecification',
                'name' => $amenity->name,
                'value' => true,
            ])->all(),
            'makesOffer' => $venue->resources->map(fn ($resource) => [
                '@type' => 'Offer',
                'name' => $resource->name.' hourly rental',
                'url' => route('marketplace.venues.show', $venue->slug),
                'price' => $resource->base_hourly_rate,
                'priceCurrency' => $resource->currency,
                'priceSpecification' => [
                    '@type' => 'UnitPriceSpecification',
                    'price' => $resource->base_hourly_rate,
                    'priceCurrency' => $resource->currency,
                    'unitText' => 'HOUR',
                ],
                'itemOffered' => [
                    '@type' => 'Service',
                    'name' => $resource->name.' rental',
                ],
            ])->all(),
        ];

        if (
            $venue->latitude !== null
            && $venue->longitude !== null
            && $venue->coordinates_verified_at !== null
        ) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
            ];
        }

        if ((int) ($venue->published_reviews_count ?? 0) > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $venue->published_reviews_avg_rating, 1, '.', ''),
                'reviewCount' => (int) $venue->published_reviews_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
            $schema['review'] = $venue->reviews->map(fn ($review) => array_filter([
                '@type' => 'Review',
                'datePublished' => $review->published_at?->toDateString(),
                'reviewBody' => $review->body,
                'author' => [
                    '@type' => 'Person',
                    'name' => $review->reviewerDisplayName(),
                ],
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => $review->rating,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ],
            ], fn ($value) => $value !== null && $value !== ''))->all();
        }

        return array_filter($schema, fn ($value) => $value !== null && $value !== []);
    }
}
