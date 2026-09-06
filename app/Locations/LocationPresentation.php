<?php

namespace App\Locations;

use Illuminate\Support\Str;

final class LocationPresentation
{
    public function canonicalName(string $name, ?string $locationCode = null): string
    {
        return $this->definition($name, $locationCode)['canonical_name'] ?? $this->clean($name);
    }

    public function displayName(string $name, ?string $locationCode = null): string
    {
        return $this->definition($name, $locationCode)['display_name'] ?? $this->clean($name);
    }

    public function seoName(string $name, ?string $locationCode = null): string
    {
        return $this->definition($name, $locationCode)['seo_name'] ?? $this->displayName($name, $locationCode);
    }

    /** @return array<int, string> */
    public function aliases(string $name, ?string $locationCode = null): array
    {
        $definition = $this->definition($name, $locationCode);

        if ($definition === null) {
            return [$this->clean($name)];
        }

        return collect([
            $definition['canonical_name'],
            $definition['display_name'],
            $definition['seo_name'],
            ...$definition['aliases'],
        ])->map(fn (string $alias) => $this->clean($alias))
            ->filter()
            ->unique(fn (string $alias) => $this->normalize($alias))
            ->values()
            ->all();
    }

    public function canonicalCode(string $name, ?string $locationCode = null): ?string
    {
        return $this->definition($name, $locationCode)['code'] ?? $locationCode;
    }

    public function canonicalSlug(string $name, ?string $locationCode = null): string
    {
        return Str::slug($this->canonicalName($name, $locationCode));
    }

    /**
     * @return array{
     *     code: string,
     *     canonical_name: string,
     *     display_name: string,
     *     seo_name: string,
     *     aliases: array<int, string>
     * }|null
     */
    private function definition(string $name, ?string $locationCode): ?array
    {
        /** @var array<string, array{canonical_name: string, display_name: string, seo_name: string, aliases: array<int, string>}> $locations */
        $locations = config('location_presentation.locations', []);

        if ($locationCode !== null && isset($locations[$locationCode])) {
            return ['code' => $locationCode, ...$locations[$locationCode]];
        }

        $needle = $this->normalize($name);

        foreach ($locations as $code => $location) {
            $names = [
                $location['canonical_name'],
                $location['display_name'],
                $location['seo_name'],
                ...$location['aliases'],
            ];

            if (collect($names)->contains(fn (string $candidate) => $this->normalize($candidate) === $needle)) {
                return ['code' => (string) $code, ...$location];
            }
        }

        return null;
    }

    private function clean(string $name): string
    {
        return Str::squish($name);
    }

    private function normalize(string $name): string
    {
        return Str::lower($this->clean($name));
    }
}
