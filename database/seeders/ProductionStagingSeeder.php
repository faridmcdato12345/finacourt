<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionStagingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PlatformAdminSeeder::class,
            PsgcLocationSeeder::class,
            SportSeeder::class,
            AmenitySeeder::class,
        ]);
    }
}
