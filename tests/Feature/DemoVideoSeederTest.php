<?php

namespace Tests\Feature;

use App\Enums\AnalyticsEventType;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\ExternalBookingDestination;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Promotion;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Venue;
use App\Models\VisibilityLink;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoVideoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DemoVideoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_isolated_video_data_idempotently(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        $this->seed(DemoVideoSeeder::class);
        $this->seed(DemoVideoSeeder::class);

        $venue = Venue::query()->where('slug', DemoVideoSeeder::VENUE_SLUG)->firstOrFail();
        $videoPlayer = User::query()->where('email', config('demo-video.player_email'))->firstOrFail();
        $videoStaff = User::query()->where('email', 'demo.video.staff@finacourt.test')->firstOrFail();

        $this->assertSame('FinACourt Demo Courts Makati', $venue->name);
        $this->assertTrue($venue->is_published);
        $this->assertCount(3, $venue->resources);
        $this->assertTrue($venue->loyalty_active);
        $this->assertSame(5, $venue->loyalty_stamps_required);
        $this->assertSame(7, OperatingHour::query()->where('venue_id', $venue->getKey())->count());
        $this->assertSame(12, Booking::query()
            ->where('venue_id', $venue->getKey())
            ->where('reference', 'like', 'BK-VIDEO-202609-%')
            ->count());
        $promotion = Promotion::query()
            ->where('campaign_token', 'DEMO-FILL-SLOW-HOURS-15')
            ->firstOrFail();
        $this->assertSame('Weekday Afternoon Court Deal', $promotion->title);
        $this->assertTrue($promotion->is_active);
        $this->assertSame(2, $promotion->bookings()->count());
        $this->assertSame(4, Booking::query()
            ->where('venue_id', $venue->getKey())
            ->where('player_user_id', $videoPlayer->getKey())
            ->count());
        $this->assertSame(3, DB::table('loyalty_stamps')
            ->where('venue_id', $venue->getKey())
            ->where('player_user_id', $videoPlayer->getKey())
            ->count());
        $this->assertSame(1, RefundRequest::query()->where('reference', 'RFD-VIDEO-PENDING')->count());
        $this->assertSame(1, Membership::query()
            ->where('organization_id', $venue->organization_id)
            ->where('user_id', $videoStaff->getKey())
            ->whereNull('suspended_at')
            ->count());
        $this->assertSame(5, VisibilityLink::query()->where('venue_id', $venue->getKey())->count());
        $this->assertSame(1, ExternalBookingDestination::query()->where('venue_id', $venue->getKey())->count());
        $this->assertSame(235, AnalyticsEvent::query()
            ->where('venue_id', $venue->getKey())
            ->where('is_demo', true)
            ->count());
        $this->assertSame(135, AnalyticsEvent::query()
            ->where('venue_id', $venue->getKey())
            ->where('event_type', AnalyticsEventType::ExternalBookingLinkClick)
            ->count());
    }

    public function test_it_rejects_non_test_demo_account_addresses(): void
    {
        config(['demo-video.owner_email' => 'owner@example.com']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('reserved .test domain');

        $this->seed(DemoVideoSeeder::class);
    }
}
