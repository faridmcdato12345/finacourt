<?php

namespace Tests\Feature;

use App\Locations\LocationPresentation;
use Tests\TestCase;

class LocationPresentationTest extends TestCase
{
    public function test_configured_city_aliases_resolve_to_one_canonical_location(): void
    {
        $locations = app(LocationPresentation::class);

        foreach (['Marawi', 'Marawi City', 'City of Marawi'] as $alias) {
            $this->assertSame('City of Marawi', $locations->canonicalName($alias));
            $this->assertSame('Marawi City', $locations->displayName($alias));
            $this->assertSame('Marawi City', $locations->seoName($alias));
            $this->assertSame('1903617000', $locations->canonicalCode($alias));
            $this->assertSame('city-of-marawi', $locations->canonicalSlug($alias));
        }

        $this->assertEqualsCanonicalizing(
            ['Marawi', 'Marawi City', 'City of Marawi'],
            $locations->aliases('City of Marawi'),
        );
    }

    public function test_only_explicit_city_names_are_changed(): void
    {
        $locations = app(LocationPresentation::class);

        $this->assertSame('Iligan City', $locations->displayName('City of Iligan'));
        $this->assertSame('Davao City', $locations->displayName('City of Davao'));
        $this->assertSame('Cebu City', $locations->displayName('City of Cebu'));
        $this->assertSame('Bunawan', $locations->displayName('Bunawan'));
        $this->assertSame('Municipality of Example', $locations->displayName('Municipality of Example'));
        $this->assertSame('City of Example', $locations->displayName('City of Example'));
    }
}
