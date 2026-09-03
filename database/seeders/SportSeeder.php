<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportSeeder extends Seeder
{
    /** @var array<int, array{name: string, slug: string}> */
    private const SPORTS = [
        ['name' => 'Badminton', 'slug' => 'badminton'],
        ['name' => 'Basketball', 'slug' => 'basketball'],
        ['name' => 'Futsal', 'slug' => 'futsal'],
        ['name' => 'Pickleball', 'slug' => 'pickleball'],
        ['name' => 'Tennis', 'slug' => 'tennis'],
        ['name' => 'Volleyball', 'slug' => 'volleyball'],
    ];

    public function run(): void
    {
        foreach (self::SPORTS as $sport) {
            Sport::query()->updateOrCreate(
                ['slug' => $sport['slug']],
                ['name' => $sport['name'], 'is_active' => true],
            );
        }

        $this->command?->info(count(self::SPORTS).' sports seeded.');
    }
}
