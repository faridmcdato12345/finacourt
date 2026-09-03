<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Sport;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\SportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sport_seeder_creates_the_active_production_catalog_idempotently(): void
    {
        Sport::query()->create([
            'name' => 'Badminton',
            'slug' => 'badminton',
            'is_active' => false,
        ]);
        Sport::query()->create([
            'name' => 'Padel',
            'slug' => 'padel',
            'is_active' => true,
        ]);

        $this->seed(SportSeeder::class);
        $this->seed(SportSeeder::class);

        $this->assertSame(7, Sport::query()->count());
        $this->assertSame(
            ['badminton', 'basketball', 'futsal', 'pickleball', 'tennis', 'volleyball'],
            Sport::query()
                ->whereIn('slug', ['badminton', 'basketball', 'futsal', 'pickleball', 'tennis', 'volleyball'])
                ->where('is_active', true)
                ->orderBy('slug')
                ->pluck('slug')
                ->all(),
        );
        $this->assertDatabaseHas('sports', [
            'name' => 'Padel',
            'slug' => 'padel',
            'is_active' => true,
        ]);
    }

    public function test_amenity_seeder_creates_the_active_production_catalog_idempotently(): void
    {
        Amenity::query()->create([
            'name' => 'Parking',
            'slug' => 'parking',
            'is_active' => false,
        ]);
        Amenity::query()->create([
            'name' => 'Cafe',
            'slug' => 'cafe',
            'is_active' => true,
        ]);

        $this->seed(AmenitySeeder::class);
        $this->seed(AmenitySeeder::class);

        $this->assertSame(8, Amenity::query()->count());
        $this->assertSame(
            ['equipment-rental', 'lockers', 'parking', 'restrooms', 'seating', 'showers', 'water-station'],
            Amenity::query()
                ->whereIn('slug', ['equipment-rental', 'lockers', 'parking', 'restrooms', 'seating', 'showers', 'water-station'])
                ->where('is_active', true)
                ->orderBy('slug')
                ->pluck('slug')
                ->all(),
        );
        $this->assertDatabaseHas('amenities', [
            'name' => 'Cafe',
            'slug' => 'cafe',
            'is_active' => true,
        ]);
    }
}
