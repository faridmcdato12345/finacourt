<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VenueReviewAndMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_submit_one_review_only_after_their_confirmed_booking_ends(): void
    {
        [$venue, $resource] = $this->publicInventory();
        $player = User::factory()->create(['name' => 'Jamie Santos']);
        $otherPlayer = User::factory()->create();
        $booking = $this->pastBooking($venue, $resource, $player);

        $this->actingAs($player)
            ->get(route('player.bookings.show', $booking->reference))
            ->assertOk()
            ->assertSee('How was your visit?');

        $this->actingAs($otherPlayer)
            ->post(route('player.bookings.review.store', $booking->reference), [
                'rating' => 5,
                'body' => 'Should never be stored.',
            ])->assertNotFound();

        $this->actingAs($player)
            ->post(route('player.bookings.review.store', $booking->reference), [
                'rating' => 5,
                'body' => 'Clean court and helpful staff.',
            ])->assertRedirect(route('player.bookings.show', $booking->reference));

        $review = VenueReview::query()->sole();
        $this->assertSame($booking->organization_id, $review->organization_id);
        $this->assertSame($venue->getKey(), $review->venue_id);
        $this->assertSame($resource->getKey(), $review->resource_id);
        $this->assertSame($player->getKey(), $review->player_user_id);
        $this->assertSame(ReviewStatus::Pending, $review->status);

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertDontSee('Clean court and helpful staff.');

        $this->actingAs($player)
            ->post(route('player.bookings.review.store', $booking->reference), [
                'rating' => 4,
            ])->assertForbidden();
        $this->assertDatabaseCount('venue_reviews', 1);
    }

    public function test_future_and_cancelled_bookings_cannot_be_reviewed(): void
    {
        [$venue, $resource] = $this->publicInventory();
        $player = User::factory()->create();
        $future = Booking::factory()->for($resource, 'resource')->create([
            'player_user_id' => $player->getKey(),
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Marketplace,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);
        $cancelled = $this->pastBooking($venue, $resource, $player, BookingStatus::Cancelled);

        foreach ([$future, $cancelled] as $booking) {
            $this->actingAs($player)
                ->post(route('player.bookings.review.store', $booking->reference), ['rating' => 5])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('venue_reviews', 0);
    }

    public function test_platform_admin_moderates_reviews_and_only_published_reviews_are_public(): void
    {
        [$venue, $resource] = $this->publicInventory();
        $player = User::factory()->create(['name' => 'Alex Rivera']);
        $booking = $this->pastBooking($venue, $resource, $player);
        $review = $this->reviewFor($booking, 'Excellent surface and friendly team.');
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($venue->organization)->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($owner)->get(route('platform.reviews.index'))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('platform.reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Reviews/Index')
                ->has('reviews', 1)
                ->where('reviews.0.id', $review->getKey())
                ->where('reviews.0.booking.reference', $booking->reference));

        $this->actingAs($admin)
            ->patch(route('platform.reviews.update', $review), [
                'status' => ReviewStatus::Published->value,
            ])->assertRedirect();

        $review->refresh();
        $this->assertSame(ReviewStatus::Published, $review->status);
        $this->assertSame($admin->getKey(), $review->moderated_by_user_id);
        $this->assertNotNull($review->published_at);

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertSee('Excellent surface and friendly team.')
            ->assertSee('Alex R.')
            ->assertSee('Verified booking')
            ->assertSee('"@type":"AggregateRating"', false)
            ->assertSee('"ratingValue":"5.0"', false)
            ->assertSee('"reviewCount":1', false);

        $secondPlayer = User::factory()->create();
        $rejected = $this->reviewFor(
            $this->pastBooking($venue, $resource, $secondPlayer),
            'Content that should not be public.',
        );
        $this->actingAs($admin)->patch(route('platform.reviews.update', $rejected), [
            'status' => ReviewStatus::Rejected->value,
            'moderation_note' => 'Not relevant to the venue visit.',
        ])->assertRedirect();

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertDontSee('Content that should not be public.');
    }

    public function test_owner_verified_coordinates_render_attributed_map_and_unverified_coordinates_do_not(): void
    {
        [$venue] = $this->publicInventory([
            'latitude' => '7.0731000',
            'longitude' => '125.6128000',
            'coordinates_source' => 'owner',
            'coordinates_verified_at' => now(),
        ]);

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertSee('openstreetmap.org/export/embed.html', false)
            ->assertSee('marker=7.0731%2C125.6128', false)
            ->assertSee('OpenStreetMap contributors')
            ->assertSee('Map showing '.$venue->name);

        $venue->update(['coordinates_verified_at' => null]);

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertDontSee('openstreetmap.org/export/embed.html', false)
            ->assertSee('A map will appear after the venue owner verifies its coordinates.');
    }

    public function test_owner_coordinate_updates_require_a_pair_and_mark_the_pin_verified(): void
    {
        [$venue] = $this->publicInventory(['latitude' => null, 'longitude' => null]);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($venue->organization)->create();
        $sport = $venue->sports->firstOrFail();

        $base = [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'description' => $venue->description,
            'address' => $venue->address,
            'city' => $venue->city,
            'province' => $venue->province,
            'phone' => null,
            'email' => null,
            'website' => null,
            'is_published' => true,
            'sports' => [$sport->getKey()],
            'amenities' => [],
        ];

        $this->actingAs($owner)
            ->put(route('owner.venues.update', $venue), [
                ...$base,
                'latitude' => '7.0731000',
                'longitude' => null,
            ])->assertSessionHasErrors('longitude');

        $this->actingAs($owner)
            ->put(route('owner.venues.update', $venue), [
                ...$base,
                'latitude' => '7.0731000',
                'longitude' => '125.6128000',
            ])->assertRedirect(route('owner.venues.show', $venue));

        $venue->refresh();
        $this->assertSame('owner', $venue->coordinates_source);
        $this->assertNotNull($venue->coordinates_verified_at);
    }

    /** @return array{Venue, CourtResource} */
    private function publicInventory(array $venueAttributes = []): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => 'Davao Review Courts',
            'slug' => 'davao-review-courts',
            'city' => 'Davao City',
            'city_slug' => 'davao-city',
            'province' => 'Davao del Sur',
            'province_slug' => 'davao-del-sur',
            ...$venueAttributes,
        ]);
        $sport = Sport::factory()->create(['name' => 'Pickleball', 'slug' => 'pickleball']);
        $venue->sports()->attach($sport);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'is_active' => true,
            'booking_increment_minutes' => 60,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        return [$venue, $resource];
    }

    private function pastBooking(
        Venue $venue,
        CourtResource $resource,
        User $player,
        BookingStatus $status = BookingStatus::Confirmed,
    ): Booking {
        return Booking::factory()->for($resource, 'resource')->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'player_user_id' => $player->getKey(),
            'status' => $status,
            'source' => BookingSource::Marketplace,
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDays(2)->addHour(),
        ]);
    }

    private function reviewFor(Booking $booking, string $body): VenueReview
    {
        return VenueReview::query()->create([
            'organization_id' => $booking->organization_id,
            'venue_id' => $booking->venue_id,
            'resource_id' => $booking->resource_id,
            'booking_id' => $booking->getKey(),
            'player_user_id' => $booking->player_user_id,
            'rating' => 5,
            'body' => $body,
            'status' => ReviewStatus::Pending,
        ]);
    }
}
