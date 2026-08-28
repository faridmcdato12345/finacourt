<?php

namespace Database\Factories;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'reference' => 'PAY-'.Str::ulid(),
            'provider' => 'manual',
            'mode' => PaymentMode::PayAtVenue,
            'status' => PaymentStatus::Pending,
            'refunded_amount' => '0.00',
            'currency' => 'PHP',
            'provider_reference' => null,
            'requires_review' => false,
            'review_reason' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Payment $payment): void {
            $payment->organization_id ??= $payment->booking->organization_id;
            $payment->amount ??= $payment->booking->player_total_amount;
            $payment->venue_amount ??= $payment->booking->total_amount;
            $payment->platform_service_fee_amount ??= $payment->booking->platform_service_fee_amount;
            $payment->currency ??= $payment->booking->currency;
        });
    }
}
