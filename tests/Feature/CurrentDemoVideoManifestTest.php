<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CurrentDemoVideoManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(base_path('output/demo-video/tutorial-manifest.json'));

        parent::tearDown();
    }

    public function test_it_builds_a_manifest_without_mutating_approved_demo_records(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $venue = Venue::factory()->for($organization)->create([
            'name' => 'Demo Courts Makati',
            'slug' => 'demo-courts-makati',
            'is_published' => true,
        ]);
        $sport = Sport::factory()->create();
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'booking_increment_minutes' => 60,
            'is_active' => true,
        ]);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $player = User::factory()->create(['email' => 'player@example.com']);
        Membership::factory()->for($organization)->for($owner)->owner()->create([
            'role' => MembershipRole::Owner,
        ]);
        $booking = Booking::factory()->for($organization)->for($venue)->for($resource, 'resource')->create([
            'player_user_id' => $player->getKey(),
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Manual,
        ]);
        $promotion = Promotion::factory()->for($organization)->for($venue)->create(['is_active' => true]);

        config(['demo-video.current_development' => [
            'venue_slug' => $venue->slug,
            'owner_email' => $owner->email,
            'player_email' => $player->email,
            'account_password' => 'password',
        ]]);
        $before = [
            'bookings' => Booking::count(),
            'promotions' => Promotion::count(),
            'memberships' => Membership::count(),
        ];

        $this->artisan('finacourt:demo-video-current-manifest --json')
            ->expectsOutputToContain('current-development-data')
            ->assertSuccessful();

        $manifest = json_decode(File::get(base_path('output/demo-video/tutorial-manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($venue->getKey(), $manifest['venue_id']);
        $this->assertSame($resource->getKey(), $manifest['resource_id']);
        $this->assertSame($booking->reference, $manifest['booking_reference']);
        $this->assertSame($promotion->getKey(), $manifest['promotion_id']);
        $this->assertSame($before, [
            'bookings' => Booking::count(),
            'promotions' => Promotion::count(),
            'memberships' => Membership::count(),
        ]);
    }

    public function test_it_refuses_a_non_demo_venue_before_writing_a_manifest(): void
    {
        $venue = Venue::factory()->create(['name' => 'Production-Like Courts', 'slug' => 'production-like-courts']);

        config(['demo-video.current_development.venue_slug' => $venue->slug]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('explicitly labelled Demo venue');

        $this->artisan('finacourt:demo-video-current-manifest');
    }
}
