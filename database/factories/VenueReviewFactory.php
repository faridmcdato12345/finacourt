<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\VenueReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VenueReview> */
class VenueReviewFactory extends Factory
{
    protected $model = VenueReview::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->state(['player_user_id' => User::factory()]),
            'rating' => fake()->numberBetween(1, 5),
            'body' => fake()->optional()->paragraph(),
            'status' => ReviewStatus::Pending,
            'moderated_at' => null,
            'published_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (VenueReview $review): void {
            $booking = $review->booking;
            $review->organization_id ??= $booking->organization_id;
            $review->venue_id ??= $booking->venue_id;
            $review->resource_id ??= $booking->resource_id;
            $review->player_user_id ??= $booking->player_user_id;
        });
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::Published,
            'moderated_at' => now(),
            'published_at' => now(),
        ]);
    }
}
