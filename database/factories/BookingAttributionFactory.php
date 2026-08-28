<?php

namespace Database\Factories;

use App\Enums\AcquisitionSource;
use App\Models\Booking;
use App\Models\BookingAttribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingAttribution> */
class BookingAttributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'first_source' => AcquisitionSource::Direct,
            'first_seen_at' => now(),
            'last_source' => AcquisitionSource::Direct,
            'last_seen_at' => now(),
            'attributed_source' => AcquisitionSource::Direct,
            'attributed_at' => now(),
            'rule_version' => 'last_touch_with_promotion_override_v1',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (BookingAttribution $attribution): void {
            $attribution->organization_id ??= $attribution->booking->organization_id;
            $attribution->venue_id ??= $attribution->booking->venue_id;
        });
    }
}
