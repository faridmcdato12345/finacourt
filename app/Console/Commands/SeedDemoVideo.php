<?php

namespace App\Console\Commands;

use App\Enums\AcquisitionSource;
use App\Models\Booking;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoVideoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SeedDemoVideo extends Command
{
    protected $signature = 'finacourt:demo-video-seed {--json : Print the generated manifest as JSON}';

    protected $description = 'Create isolated local demo data used by the FinACourt product-video generator';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => DemoVideoSeeder::class,
            '--force' => true,
        ]);

        $venue = Venue::query()
            ->with(['organization', 'resources' => fn ($query) => $query->orderBy('id')])
            ->where('slug', DemoVideoSeeder::VENUE_SLUG)
            ->firstOrFail();
        $link = $venue->visibilityLinks()
            ->where('acquisition_source', AcquisitionSource::Facebook->value)
            ->whereNull('campaign')
            ->firstOrFail();
        $resource = $venue->resources->first()
            ?? throw new RuntimeException('The demo-video venue has no court resource.');
        $refundBooking = Booking::query()
            ->where('venue_id', $venue->getKey())
            ->where('reference', 'BK-VIDEO-REFUND-DEMO')
            ->firstOrFail();
        $bookingDate = CarbonImmutable::now($venue->organization->timezone)->addDay()->toDateString();
        $outputDirectory = base_path('output/demo-video');

        File::ensureDirectoryExists($outputDirectory);

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'venue_id' => $venue->getKey(),
            'venue_name' => $venue->name,
            'venue_slug' => $venue->slug,
            'resource_id' => $resource->getKey(),
            'resource_name' => $resource->name,
            'booking_date' => $bookingDate,
            'booking_start' => '14:00',
            'booking_duration' => 60,
            'refund_booking_reference' => $refundBooking->reference,
            'owner_email' => config('demo-video.owner_email'),
            'player_email' => config('demo-video.player_email'),
            'external_link_token' => $link->token,
        ];
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        File::put($outputDirectory.'/manifest.json', $json);

        if ($this->option('json')) {
            $this->line($json);
        } else {
            $this->info('Demo-video data is ready at output/demo-video/manifest.json.');
        }

        return self::SUCCESS;
    }
}
