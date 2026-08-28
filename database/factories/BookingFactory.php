<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(2)->startOfHour();

        return [
            'resource_id' => CourtResource::factory(),
            'reference' => 'BK-'.Str::ulid(),
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Manual,
            'traffic_source' => null,
            'traffic_source_detail' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'notes' => null,
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'expires_at' => null,
            'timezone' => 'Asia/Manila',
            'unit_price' => '650.00',
            'original_unit_price' => '650.00',
            'total_amount' => '650.00',
            'original_total_amount' => '650.00',
            'discount_amount' => '0.00',
            'platform_service_fee_rule_id' => null,
            'platform_service_fee_name' => null,
            'platform_service_fee_type' => null,
            'platform_service_fee_rate_basis_points' => null,
            'platform_service_fee_fixed_amount' => null,
            'platform_service_fee_amount' => '0.00',
            'currency' => 'PHP',
            'created_by_user_id' => User::factory(),
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
            'cancellation_reason' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Booking $booking): void {
            $booking->venue_id ??= $booking->resource->venue_id;
            $booking->organization_id ??= $booking->resource->venue->organization_id;
            $booking->timezone ??= $booking->resource->venue->organization->timezone;
            $booking->player_total_amount ??= number_format(
                ((float) $booking->total_amount) + ((float) $booking->platform_service_fee_amount),
                2,
                '.',
                '',
            );
        });
    }

    public function hold(int $minutes = 15): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Hold,
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Hold,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'expires_at' => null,
        ]);
    }
}
