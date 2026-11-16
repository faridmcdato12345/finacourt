<?php

namespace App\Bookings;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Models\Venue;
use App\Payments\CreatePaymentAttempt;
use App\Payments\PaymentProviderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Creates every selected court hold atomically, with one payment attempt for the group. */
class CreateReservationGroup
{
    public function __construct(
        private readonly CreateBooking $bookings,
        private readonly CreatePaymentAttempt $paymentAttempts,
        private readonly PaymentProviderRegistry $providers,
    ) {}

    /** @param array<int, array{resource_id:int,booking_date:string,start_time:string,end_time:string,campaign:?string}> $selections */
    public function handle(Venue $venue, User $player, array $selections, string $customerName, ?string $customerPhone, string $providerKey): ReservationGroup
    {
        $provider = $this->providers->find($providerKey);
        if ($provider === null) {
            throw ValidationException::withMessages(['payment_option' => 'The selected payment method is unavailable.']);
        }

        return DB::transaction(function () use ($venue, $player, $selections, $customerName, $customerPhone, $provider, $providerKey): ReservationGroup {
            $group = ReservationGroup::query()->create([
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'player_user_id' => $player->getKey(),
                'reference' => 'RG-'.Str::ulid(),
                'status' => BookingStatus::Hold->value,
                'expires_at' => now()->addMinutes((int) config('booking.hold_minutes')),
            ]);

            $bookings = collect();
            foreach (collect($selections)->sortBy(['resource_id', 'start_time']) as $selection) {
                $booking = $this->bookings->handle($venue->organization_id, $player, [
                    'resource_id' => $selection['resource_id'],
                    'booking_date' => $selection['booking_date'],
                    'start_time' => $selection['start_time'],
                    'end_time' => $selection['end_time'],
                    'status' => BookingStatus::Hold->value,
                    'source' => BookingSource::Marketplace->value,
                    'hold_minutes' => config('booking.hold_minutes'),
                    'customer_name' => $customerName,
                    'customer_email' => $player->email,
                    'customer_phone' => $customerPhone,
                    'create_payment' => false,
                    'campaign' => $selection['campaign'],
                ], $player);
                $booking->update([
                    'reservation_group_id' => $group->getKey(),
                    'payment_mode' => $provider->mode(),
                    'payment_status' => PaymentStatus::Pending,
                    // Every court in the group retains its own eligibility snapshot,
                    // while VenueLoyalty::award enforces one stamp per player,
                    // venue-local day across all of them.
                    'loyalty_eligible' => $venue->loyalty_active
                        && $provider->mode() === PaymentMode::HostedCheckout
                        && $booking->start_at->diffInMinutes($booking->end_at) >= 60,
                    'loyalty_terms_version' => $venue->loyalty_active && $provider->mode() === PaymentMode::HostedCheckout
                        ? $venue->loyalty_terms_version : null,
                    'loyalty_stamps_required' => $venue->loyalty_active && $provider->mode() === PaymentMode::HostedCheckout
                        ? $venue->loyalty_stamps_required : null,
                    'loyalty_discount_percent' => $venue->loyalty_active && $provider->mode() === PaymentMode::HostedCheckout
                        ? $venue->loyalty_discount_percent : null,
                    'loyalty_discount_cap' => $venue->loyalty_active && $provider->mode() === PaymentMode::HostedCheckout
                        ? $venue->loyalty_discount_cap : null,
                ]);
                $bookings->push($booking->fresh());
            }

            $expiresAt = $bookings->min('expires_at');
            $group->update(['expires_at' => $expiresAt]);
            $anchor = $bookings->first();
            $payment = Payment::query()->create([
                'organization_id' => $venue->organization_id,
                'reservation_group_id' => $group->getKey(),
                'booking_id' => $anchor->getKey(),
                'reference' => 'PAY-'.Str::ulid(),
                'provider' => $provider->key(),
                'mode' => $provider->mode(),
                'status' => PaymentStatus::Pending,
                'amount' => $bookings->sum(fn (Booking $booking) => (float) $booking->player_total_amount),
                'venue_amount' => $bookings->sum(fn (Booking $booking) => (float) $booking->total_amount),
                'platform_service_fee_amount' => $bookings->sum(fn (Booking $booking) => (float) $booking->platform_service_fee_amount),
                'currency' => $anchor->currency,
                'created_by_user_id' => $player->getKey(),
            ]);
            $payment->transitions()->create(['to_status' => PaymentStatus::Pending, 'source' => 'application', 'actor_user_id' => $player->getKey(), 'note' => 'One payment attempt created for grouped court holds.']);

            return $group->load(['bookings.resource', 'payments']);
        }, 5);
    }
}
