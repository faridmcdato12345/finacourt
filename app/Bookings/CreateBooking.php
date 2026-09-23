<?php

namespace App\Bookings;

use App\Analytics\SnapshotBookingAttribution;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Loyalty\VenueLoyalty;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\User;
use App\Models\Venue;
use App\Payments\CreatePaymentAttempt;
use App\Payments\PlatformServiceFeeCalculator;
use App\Promotions\PromotionApplicability;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingReference $references,
        private readonly BookingPrice $prices,
        private readonly CreatePaymentAttempt $createPayment,
        private readonly PlatformServiceFeeCalculator $serviceFees,
        private readonly PromotionApplicability $promotions,
        private readonly SnapshotBookingAttribution $attribution,
        private readonly VenueLoyalty $loyalty,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(int $organizationId, User $creator, array $data, ?User $player = null): Booking
    {
        return DB::transaction(function () use ($organizationId, $creator, $data, $player): Booking {
            // The resource row is the mutex for its booking calendar. Every booking
            // writer follows this protocol, serializing overlap checks and inserts.
            $resource = CourtResource::query()
                ->whereKey($data['resource_id'])
                ->whereHas('venue', fn ($query) => $query->where('organization_id', $organizationId))
                ->lockForUpdate()
                ->first();

            if (! $resource) {
                throw (new ModelNotFoundException)->setModel(CourtResource::class, [$data['resource_id']]);
            }

            $resource->load('venue.organization');
            // A venue row serializes reward reservations and owner toggles.
            $venue = Venue::query()->whereKey($resource->venue_id)->lockForUpdate()->firstOrFail();
            $window = $this->availability->window(
                $resource,
                $data['booking_date'],
                $data['start_time'],
                $data['end_time'],
            );
            $this->availability->ensureBookable($resource, $window);

            if ($this->availability->hasConflict($resource->getKey(), $window->utcStart, $window->utcEnd)) {
                throw ValidationException::withMessages([
                    'start_time' => 'This time overlaps an active reservation, hold, or court block.',
                ]);
            }

            $status = BookingStatus::from($data['status']);
            $promotion = $this->promotions->resolve(
                $resource,
                $window,
                $data['campaign'] ?? null,
                lockForUpdate: true,
            );
            $price = $this->prices->quote($resource, $window->durationMinutes, $promotion, $window);
            $paymentProvider = ($data['create_payment'] ?? false)
                ? ($data['payment_provider'] ?? null)
                : null;
            $paymentMode = ($data['create_payment'] ?? false)
                ? $this->createPayment->mode($paymentProvider)
                : null;
            $loyaltyDiscount = null;

            $rewardVersion = isset($data['loyalty_reward_version']) ? (int) $data['loyalty_reward_version'] : null;
            $reward = $rewardVersion !== null && $player !== null
                ? collect($this->loyalty->balance($venue, $player)['rewards'])
                    ->first(fn (array $item): bool => $item['version'] === $rewardVersion && $item['rewards_available'] > 0)
                : null;

            if ($rewardVersion !== null) {
                if ($player === null
                    || $data['source'] !== BookingSource::Marketplace->value
                    || $paymentMode !== PaymentMode::HostedCheckout
                    || $reward === null) {
                    throw ValidationException::withMessages([
                        'loyalty' => 'That loyalty reward is no longer available for this booking. Review the price and try again.',
                    ]);
                }

                $quote = $this->loyalty->discountedQuote(
                    $price, $window->durationMinutes, $reward['discount_percent'], $reward['discount_cap'],
                );
                if ($quote['discount'] === '0.00') {
                    throw ValidationException::withMessages([
                        'loyalty' => 'This reward would not reduce the court price. Save it for another booking.',
                    ]);
                }
                if ($this->serviceFees->quote($quote['price']['total_amount'], $price['currency'])['player_total_amount'] === '0.00') {
                    throw ValidationException::withMessages([
                        'loyalty' => 'This reward would leave nothing payable at online checkout. Save it for another booking.',
                    ]);
                }
                $price = $quote['price'];
                $loyaltyDiscount = $quote['discount'];
            }

            $serviceFee = $paymentMode === PaymentMode::HostedCheckout
                ? $this->serviceFees->quote($price['total_amount'], $price['currency'])
                : $this->serviceFees->emptyQuoteFromAmount($price['total_amount']);
            $holdMinutes = (int) ($data['hold_minutes'] ?? config('booking.hold_minutes'));
            $holdExpiresAt = now()->addMinutes($holdMinutes);

            if ($holdExpiresAt->greaterThan($window->utcStart)) {
                $holdExpiresAt = $window->utcStart;
            }

            $loyaltyEligible = $venue->loyalty_active
                && $player !== null
                && $data['source'] === BookingSource::Marketplace->value
                && $paymentMode === PaymentMode::HostedCheckout
                && $window->durationMinutes >= 60;

            $booking = Booking::query()->create([
                'organization_id' => $organizationId,
                'venue_id' => $resource->venue_id,
                'resource_id' => $resource->getKey(),
                'promotion_id' => $promotion?->getKey(),
                'promotion_campaign_token' => $promotion?->campaign_token,
                'promotion_title' => $promotion?->title,
                'player_user_id' => $player?->getKey(),
                'reference' => $this->references->generate(),
                'status' => $status,
                'source' => $data['source'],
                'traffic_source' => $data['traffic_source'] ?? null,
                'traffic_source_detail' => $data['traffic_source_detail'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'start_at' => $window->utcStart,
                'end_at' => $window->utcEnd,
                'expires_at' => $status === BookingStatus::Hold ? $holdExpiresAt : null,
                'timezone' => $resource->venue->organization->timezone,
                ...$price,
                ...$serviceFee,
                'payment_mode' => $paymentMode,
                'payment_status' => $paymentMode !== null ? PaymentStatus::Pending : null,
                'loyalty_eligible' => $loyaltyEligible,
                'loyalty_terms_version' => $loyaltyEligible ? $venue->loyalty_terms_version : null,
                'loyalty_stamps_required' => $loyaltyEligible ? $venue->loyalty_stamps_required : null,
                'loyalty_discount_percent' => $loyaltyEligible ? $venue->loyalty_discount_percent : null,
                'loyalty_discount_cap' => $loyaltyEligible ? $venue->loyalty_discount_cap : null,
                'created_by_user_id' => $creator->getKey(),
            ]);

            if ($loyaltyDiscount !== null) {
                DB::table('loyalty_redemptions')->insert([
                    'venue_id' => $venue->getKey(),
                    'player_user_id' => $player->getKey(),
                    'booking_id' => $booking->getKey(),
                    'terms_version' => $rewardVersion,
                    'court_discount_amount' => $loyaltyDiscount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->attribution->record(
                $booking,
                $data['acquisition_context'] ?? null,
                $promotion,
                $window,
            );

            if ($booking->payment_mode !== null) {
                $this->createPayment->handle($booking, $creator, $paymentProvider);
            }

            if ($promotion !== null) {
                $promotion->increment('booking_starts_count');
            }

            return $booking;
        }, 5);
    }
}
