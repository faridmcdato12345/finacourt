<?php

namespace Tests\Feature;

use App\Analytics\AnalyticsPeriod;
use App\Analytics\AnalyticsReport;
use App\Enums\AcquisitionSource;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\BookingAttribution;
use App\Models\CourtResource;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\AnalyticsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_current_month_demo_analytics_idempotently(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 12:00:00');
        $organization = Organization::factory()->create([
            'slug' => 'demo-courts',
            'timezone' => 'Asia/Manila',
        ]);
        $venue = Venue::factory()->for($organization)->create([
            'slug' => 'demo-courts-makati',
        ]);
        $sport = Sport::factory()->create();
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => '650.00',
            'currency' => 'PHP',
            'is_active' => true,
        ]);
        Promotion::factory()->for($venue)->for($resource, 'resource')->create([
            'organization_id' => $organization->getKey(),
            'is_active' => true,
        ]);

        $this->seed(AnalyticsSeeder::class);
        $this->seed(AnalyticsSeeder::class);

        $bookings = Booking::query()
            ->where('reference', 'like', 'BK-AN-DEMO-202609-%')
            ->get();

        $this->assertCount(12, $bookings);
        $this->assertSame(12, BookingAttribution::query()
            ->whereIn('booking_id', $bookings->modelKeys())
            ->count());
        $this->assertSame(92, AnalyticsEvent::query()
            ->where('organization_id', $organization->getKey())
            ->where('is_demo', true)
            ->count());
        $this->assertSame(1, $bookings
            ->where('traffic_source', AcquisitionSource::MarketplacePromotion->value)
            ->count());

        $period = AnalyticsPeriod::fromFilters([
            'from' => '2026-09-01',
            'to' => '2026-09-12',
        ], $organization->timezone);
        $report = app(AnalyticsReport::class)->generate($period, $organization);

        $this->assertSame(12, $report['booking_source_summary']['total_bookings']);
        $this->assertSame(8, count($report['booking_source_summary']['sources']));
        $this->assertSame('finacourt_search', $report['booking_source_summary']['top_source']['key']);
        $this->assertSame(3, $report['booking_source_summary']['top_source']['bookings']);
        $this->assertSame(24, $report['metrics']['profile_views']);
        $this->assertSame(12, $report['metrics']['completed_bookings']);
        $this->assertSame(50.0, $report['metrics']['conversion_rate']);
    }
}
