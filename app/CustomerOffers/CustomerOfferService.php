<?php

namespace App\CustomerOffers;

use App\Enums\CustomerOfferDiscountType;
use App\Enums\CustomerOfferStatus;
use App\Models\Booking;
use App\Models\CustomerOffer;
use App\Models\CustomerOfferCampaign;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer offers deliberately live outside the public Promotion engine.
 * All money calculations use integer centavos and all state changes happen
 * under database locks so an offer cannot be spent twice.
 */
class CustomerOfferService
{
    /** @return array{offer: CustomerOffer, discount_amount: string, total_amount: string}|null */
    public function bestAvailable(Venue $venue, User $user, string $subtotal, bool $lock = false): ?array
    {
        $now = now('UTC');
        if ($lock) {
            $this->releaseExpiredReservations($now);
            $this->expireDueOffers($now);
        }

        $query = CustomerOffer::query()
            ->with('campaign')
            ->where('organization_id', $venue->organization_id)
            ->where('venue_id', $venue->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', CustomerOfferStatus::Available)
            ->where('expires_at', '>', $now)
            ->orderBy('expires_at');
        if ($lock) {
            $query->lockForUpdate();
        }

        $best = null;
        foreach ($query->get() as $offer) {
            $campaign = $lock
                ? CustomerOfferCampaign::query()->whereKey($offer->customer_offer_campaign_id)->lockForUpdate()->first()
                : $offer->campaign;

            if (! $campaign || ! $this->campaignCanRedeem($campaign, $now)) {
                continue;
            }

            $quote = $this->quote($offer, $subtotal);
            if ($quote === null) {
                continue;
            }

            if ($best === null
                || $this->cents($quote['discount_amount']) > $this->cents($best['discount_amount'])
                || ($this->cents($quote['discount_amount']) === $this->cents($best['discount_amount'])
                    && $offer->expires_at->lessThan($best['offer']->expires_at))) {
                $best = ['offer' => $offer, ...$quote];
            }
        }

        return $best;
    }

    /** @return array{discount_amount: string, total_amount: string}|null */
    public function quote(CustomerOffer $offer, string $subtotal): ?array
    {
        $subtotalCents = $this->cents($subtotal);
        $minimumCents = $offer->minimum_spend === null ? 0 : $this->cents($offer->minimum_spend);
        if ($subtotalCents <= 0 || $subtotalCents < $minimumCents) {
            return null;
        }

        $discountCents = match ($offer->discount_type) {
            CustomerOfferDiscountType::Fixed => min($subtotalCents, $this->cents($offer->discount_value)),
            CustomerOfferDiscountType::Percentage => min($subtotalCents, intdiv(
                $subtotalCents * min(10000, $this->cents($offer->discount_value)) + 5000,
                10000,
            )),
        };

        // A hosted payment attempt must retain a payable court subtotal. This
        // mirrors the existing loyalty guard and avoids creating a zero-value
        // provider checkout that cannot be verified reliably.
        if ($discountCents <= 0 || $discountCents >= $subtotalCents) {
            return null;
        }

        return [
            'discount_amount' => $this->money($discountCents),
            'total_amount' => $this->money($subtotalCents - $discountCents),
        ];
    }

    /**
     * @param array<string, mixed> $basePrice
     * @param array{discount_amount: string, total_amount: string} $quote
     * @return array<string, mixed>
     */
    public function applyToBookingPrice(array $basePrice, array $quote, int $durationMinutes): array
    {
        $totalCents = $this->cents($quote['total_amount']);
        $effectiveHourlyCents = $durationMinutes > 0
            ? intdiv($totalCents * 60 + intdiv($durationMinutes, 2), $durationMinutes)
            : 0;

        return [
            ...$basePrice,
            'total_amount' => $quote['total_amount'],
            'discount_amount' => $quote['discount_amount'],
            'unit_price' => $this->money($effectiveHourlyCents),
        ];
    }

    /** @return array{offer: CustomerOffer, discount_amount: string, total_amount: string} */
    public function reserve(CustomerOffer $offer, Booking $booking, string $subtotal): array
    {
        $now = now('UTC');
        $offer = CustomerOffer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();
        $campaign = CustomerOfferCampaign::query()->whereKey($offer->customer_offer_campaign_id)->lockForUpdate()->firstOrFail();

        $this->assertAvailableFor($offer, $campaign, $booking->venue_id, $booking->player_user_id, $now);
        $quote = $this->quote($offer, $subtotal);
        if ($quote === null) {
            throw ValidationException::withMessages(['offer' => 'This exclusive offer no longer applies to the selected booking.']);
        }

        $reservationExpiresAt = $booking->expires_at ?? $now;
        $offer->update([
            'status' => CustomerOfferStatus::Reserved,
            'booking_id' => $booking->getKey(),
            'reserved_at' => $now,
            'reservation_expires_at' => $reservationExpiresAt,
            'released_at' => null,
        ]);

        return ['offer' => $offer->refresh(), ...$quote];
    }

    public function redeemForBooking(Booking $booking): ?CustomerOffer
    {
        if ($booking->customer_offer_id === null) {
            return null;
        }

        $offer = CustomerOffer::query()->whereKey($booking->customer_offer_id)->lockForUpdate()->firstOrFail();
        if ($offer->status === CustomerOfferStatus::Redeemed) {
            return $offer;
        }

        if ($offer->status !== CustomerOfferStatus::Reserved || $offer->booking_id !== $booking->getKey()) {
            throw ValidationException::withMessages(['offer' => 'The exclusive offer reservation is no longer valid.']);
        }

        $offer->update([
            'status' => CustomerOfferStatus::Redeemed,
            'redeemed_at' => now('UTC'),
            'reservation_expires_at' => null,
        ]);

        return $offer->refresh();
    }

    public function releaseForBooking(Booking $booking, ?CarbonInterface $at = null): void
    {
        if ($booking->customer_offer_id === null) {
            return;
        }

        $at ??= now('UTC');
        $offer = CustomerOffer::query()->whereKey($booking->customer_offer_id)->lockForUpdate()->first();
        if (! $offer || $offer->status !== CustomerOfferStatus::Reserved || $offer->booking_id !== $booking->getKey()) {
            return;
        }

        $offer->update([
            'status' => $offer->expires_at->lessThanOrEqualTo($at)
                ? CustomerOfferStatus::Expired
                : CustomerOfferStatus::Available,
            'booking_id' => null,
            'reservation_expires_at' => null,
            'released_at' => $at,
        ]);
    }

    public function issue(CustomerOfferCampaign $campaign, User $user, ?int $recipientId = null): CustomerOffer
    {
        return DB::transaction(function () use ($campaign, $user, $recipientId): CustomerOffer {
            $campaign = CustomerOfferCampaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();
            if (! $campaign->is_active) {
                throw ValidationException::withMessages(['offer' => 'This customer offer campaign is not active.']);
            }

            if ($recipientId !== null) {
                $existing = CustomerOffer::query()
                    ->where('reactivation_campaign_recipient_id', $recipientId)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            return CustomerOffer::query()->create([
                'organization_id' => $campaign->organization_id,
                'customer_offer_campaign_id' => $campaign->getKey(),
                'venue_id' => $campaign->venue_id,
                'user_id' => $user->getKey(),
                'reactivation_campaign_recipient_id' => $recipientId,
                'token_hash' => hash('sha256', Str::random(80)),
                'status' => CustomerOfferStatus::Available,
                'discount_type' => $campaign->discount_type,
                'discount_value' => $campaign->discount_value,
                'minimum_spend' => $campaign->minimum_spend,
                'expires_at' => now('UTC')->addDays($campaign->valid_days),
            ]);
        }, 3);
    }

    public function revoke(CustomerOffer $offer): CustomerOffer
    {
        return DB::transaction(function () use ($offer): CustomerOffer {
            $offer = CustomerOffer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();
            if ($offer->status === CustomerOfferStatus::Available) {
                $offer->update(['status' => CustomerOfferStatus::Revoked, 'revoked_at' => now('UTC')]);
            }

            return $offer->refresh();
        });
    }

    public function releaseExpiredReservations(?CarbonInterface $at = null): int
    {
        $at ??= now('UTC');
        return CustomerOffer::query()
            ->where('status', CustomerOfferStatus::Reserved)
            ->where('reservation_expires_at', '<=', $at)
            ->update([
                'status' => CustomerOfferStatus::Available,
                'booking_id' => null,
                'reservation_expires_at' => null,
                'released_at' => $at,
                'updated_at' => $at,
            ]);
    }

    public function sweepExpiredOffers(?CarbonInterface $at = null): int
    {
        $at ??= now('UTC');
        $released = $this->releaseExpiredReservations($at);
        $this->expireDueOffers($at);

        return $released;
    }

    private function expireDueOffers(CarbonInterface $at): void
    {
        CustomerOffer::query()
            ->where('status', CustomerOfferStatus::Available)
            ->where('expires_at', '<=', $at)
            ->update(['status' => CustomerOfferStatus::Expired, 'updated_at' => $at]);
    }

    private function campaignCanRedeem(CustomerOfferCampaign $campaign, CarbonInterface $at): bool
    {
        if ($campaign->max_redemptions === null) {
            return true;
        }

        $committed = CustomerOffer::query()
            ->where('customer_offer_campaign_id', $campaign->getKey())
            ->where(function ($query) use ($at): void {
                $query->where('status', CustomerOfferStatus::Redeemed)
                    ->orWhere(fn ($reserved) => $reserved
                        ->where('status', CustomerOfferStatus::Reserved)
                        ->where('reservation_expires_at', '>', $at));
            })
            ->count();

        return $committed < $campaign->max_redemptions;
    }

    private function assertAvailableFor(CustomerOffer $offer, CustomerOfferCampaign $campaign, int $venueId, ?int $userId, CarbonInterface $at): void
    {
        if ($userId === null
            || $offer->status !== CustomerOfferStatus::Available
            || $offer->venue_id !== $venueId
            || $offer->user_id !== $userId
            || $offer->expires_at->lessThanOrEqualTo($at)
            || ! $this->campaignCanRedeem($campaign, $at)) {
            throw ValidationException::withMessages(['offer' => 'This exclusive offer is no longer available.']);
        }
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
