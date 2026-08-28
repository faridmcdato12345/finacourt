<?php

namespace Database\Seeders;

use App\Models\PsgcLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class PsgcLocationSeeder extends Seeder
{
    /** @throws JsonException */
    public function run(): void
    {
        $path = database_path('data/psgc-2026-07-13.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read the PSGC catalog at {$path}.");
        }

        /** @var array{meta: array{version: string}, locations: array<int, array<string, string|null>>} $catalog */
        $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $now = now();

        DB::transaction(function () use ($catalog, $now): void {
            foreach ([['region'], ['province', 'area'], ['city', 'municipality']] as $levels) {
                $locations = array_filter(
                    $catalog['locations'],
                    fn (array $location) => in_array($location['level'], $levels, true),
                );

                foreach (array_chunk($locations, 500) as $chunk) {
                    $rows = array_map(fn (array $location) => [
                        ...$location,
                        'source_version' => $catalog['meta']['version'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk);

                    PsgcLocation::query()->upsert(
                        $rows,
                        ['code'],
                        ['parent_code', 'name', 'level', 'type', 'source_version', 'updated_at'],
                    );
                }
            }
        });

        $this->command?->info(count($catalog['locations']).' PSGC locations imported.');
    }
}
