<?php

namespace App\Payments;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePaymentAttempt
{
    public function __construct(private readonly PaymentProviderRegistry $providers) {}

    public function mode(): PaymentMode
    {
        return $this->providers->default()->mode();
    }

    public function handle(Booking $booking, ?User $creator = null): Payment
    {
        $provider = $this->providers->default();

        if ($provider->mode() === PaymentMode::HostedCheckout && ! $provider->supportsHostedCheckout()) {
            throw ValidationException::withMessages([
                'payment' => 'Online checkout is not fully configured. Please contact FinACourt support or switch to pay-at-venue mode.',
            ]);
        }

        $payment = Payment::query()->create([
            'organization_id' => $booking->organization_id,
            'booking_id' => $booking->getKey(),
            'reference' => $this->reference(),
            'provider' => $provider->key(),
            'mode' => $provider->mode(),
            'status' => PaymentStatus::Pending,
            'amount' => $booking->player_total_amount,
            'venue_amount' => $booking->total_amount,
            'platform_service_fee_amount' => $booking->platform_service_fee_amount,
            'currency' => $booking->currency,
            'created_by_user_id' => $creator?->getKey(),
        ]);

        $payment->transitions()->create([
            'from_status' => null,
            'to_status' => PaymentStatus::Pending,
            'source' => 'application',
            'actor_user_id' => $creator?->getKey(),
            'note' => 'Payment attempt created from the booking price snapshot.',
        ]);

        return $payment;
    }

    private function reference(): string
    {
        do {
            $reference = 'PAY-'.Str::ulid();
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
