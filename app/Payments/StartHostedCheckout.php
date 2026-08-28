<?php

namespace App\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class StartHostedCheckout
{
    public function __construct(private readonly PaymentProviderRegistry $providers) {}

    public function handle(string $bookingReference, User $player): HostedCheckout
    {
        $payment = Payment::query()
            ->whereHas('booking', fn ($query) => $query
                ->where('reference', $bookingReference)
                ->where('player_user_id', $player->getKey()))
            ->with('booking')
            ->latest('id')
            ->first();

        if (! $payment) {
            throw (new ModelNotFoundException)->setModel(Payment::class, [$bookingReference]);
        }

        if (
            $payment->booking->effectiveStatus() !== BookingStatus::Hold
            || $payment->status !== PaymentStatus::Pending
        ) {
            throw ValidationException::withMessages([
                'payment' => 'Checkout requires an active reservation hold and pending payment.',
            ]);
        }

        if ($payment->amount !== $payment->booking->player_total_amount || $payment->currency !== $payment->booking->currency) {
            throw ValidationException::withMessages([
                'payment' => 'Payment amount or currency does not match the booking snapshot.',
            ]);
        }

        $provider = $this->providers->find($payment->provider);

        if (! $provider || ! $provider->supportsHostedCheckout()) {
            throw ValidationException::withMessages([
                'payment' => 'Online checkout is not configured. Use the displayed manual payment mode.',
            ]);
        }

        // Real adapters must use payment.reference as their provider idempotency key.
        $checkout = $provider->createHostedCheckout($payment);

        if ($payment->provider_reference === null) {
            Payment::query()
                ->whereKey($payment->getKey())
                ->whereNull('provider_reference')
                ->update(['provider_reference' => $checkout->providerReference]);

            $payment->refresh();
        }

        if ($payment->provider_reference !== $checkout->providerReference) {
            throw ValidationException::withMessages([
                'payment' => 'The provider returned a conflicting checkout reference.',
            ]);
        }

        return $checkout;
    }
}
