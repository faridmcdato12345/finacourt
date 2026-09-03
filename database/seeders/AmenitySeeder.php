<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /** @var array<int, array{name: string, slug: string}> */
    private const AMENITIES = [
        ['name' => 'Equipment Rental', 'slug' => 'equipment-rental'],
        ['name' => 'Lockers', 'slug' => 'lockers'],
        ['name' => 'Parking', 'slug' => 'parking'],
        ['name' => 'Restrooms', 'slug' => 'restrooms'],
        ['name' => 'Seating', 'slug' => 'seating'],
        ['name' => 'Showers', 'slug' => 'showers'],
        ['name' => 'Water Station', 'slug' => 'water-station'],
    ];

    public function run(): void
    {
        foreach (self::AMENITIES as $amenity) {
            Amenity::query()->updateOrCreate(
                ['slug' => $amenity['slug']],
                ['name' => $amenity['name'], 'is_active' => true],
            );
        }

        $this->command?->info(count(self::AMENITIES).' amenities seeded.');
    }
}
