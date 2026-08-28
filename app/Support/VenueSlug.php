<?php

namespace App\Support;

use App\Models\Venue;
use Illuminate\Support\Str;

class VenueSlug
{
    public function generate(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $suffix = 2;

        while (Venue::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
