<?php

namespace App\Locations;

trait HasPublicLocationPresentation
{
    public function publicCityName(): string
    {
        return app(LocationPresentation::class)->displayName(
            (string) $this->getAttribute('city'),
            $this->getAttribute('psgc_city_municipality_code'),
        );
    }

    public function publicCitySeoName(): string
    {
        return app(LocationPresentation::class)->seoName(
            (string) $this->getAttribute('city'),
            $this->getAttribute('psgc_city_municipality_code'),
        );
    }
}
