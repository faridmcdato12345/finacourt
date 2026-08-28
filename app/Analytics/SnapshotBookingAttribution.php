<?php

namespace App\Analytics;

use App\Bookings\BookingWindow;
use App\Enums\AcquisitionSource;
use App\Enums\BookingSource;
use App\Models\Booking;
use App\Models\BookingAttribution;
use App\Models\Promotion;
use App\Models\PromotionSlot;
use App\Models\ReactivationCampaign;
use Carbon\CarbonImmutable;
use LogicException;

class SnapshotBookingAttribution
{
    /** @param array<string, mixed>|null $context */
    public function record(
        Booking $booking,
        ?array $context,
        ?Promotion $promotion,
        BookingWindow $window,
    ): ?BookingAttribution {
        if ($booking->source !== BookingSource::Marketplace) {
            return null;
        }

        if ($promotion !== null
            && ($promotion->organization_id !== $booking->organization_id
                || $promotion->venue_id !== $booking->venue_id)) {
            throw new LogicException('Booking attribution promotion must belong to the booking tenant and venue.');
        }

        $fallback = $this->fallback($booking);
        $first = $this->touch($context['first_touch'] ?? null, $fallback);
        $last = $this->touch($context['last_touch'] ?? null, $fallback);
        $slot = null;
        $reactivation = $this->reactivationCampaign($booking, $context);

        if ($promotion !== null) {
            $last = [
                ...$last,
                'source' => AcquisitionSource::MarketplacePromotion,
                'medium' => 'promotion',
                'campaign' => $promotion->campaign_token,
                'referral_code' => null,
                'partner_code' => null,
                'seen_at' => now('UTC'),
            ];
            $slot = $this->matchingSlot($promotion, $booking, $window);
        }

        return BookingAttribution::query()->create([
            'booking_id' => $booking->getKey(),
            'organization_id' => $booking->organization_id,
            'venue_id' => $booking->venue_id,
            ...$this->attributes('first', $first),
            ...$this->attributes('last', $last),
            ...$this->attributes('attributed', $last),
            'attributed_at' => now('UTC'),
            'promotion_id' => $promotion?->getKey(),
            'promotion_campaign_token' => $promotion?->campaign_token,
            'promotion_slot_token' => $slot?->slot_token,
            'promotion_title' => $promotion?->title,
            'reactivation_campaign_id' => $reactivation?->getKey(),
            'reactivation_campaign_token' => $reactivation?->campaign_token,
            'reactivation_campaign_title' => $reactivation?->title,
            'rule_version' => config('attribution.rule_version'),
        ]);
    }

    /** @return array<string, mixed> */
    private function fallback(Booking $booking): array
    {
        return [
            'source' => AcquisitionSource::Direct,
            'medium' => null,
            'campaign' => null,
            'referral_code' => null,
            'partner_code' => null,
            'landing_path' => null,
            'referrer_host' => null,
            'seen_at' => $booking->created_at ?? now('UTC'),
        ];
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function touch(mixed $touch, array $fallback): array
    {
        if (! is_array($touch)) {
            return $fallback;
        }

        $source = $touch['source'] ?? AcquisitionSource::Unknown;
        $source = $source instanceof AcquisitionSource
            ? $source
            : AcquisitionSource::tryFrom((string) $source) ?? AcquisitionSource::Unknown;

        try {
            $seenAt = CarbonImmutable::parse((string) ($touch['seen_at'] ?? ''), 'UTC');
        } catch (\Throwable) {
            $seenAt = $fallback['seen_at'];
        }

        return [
            'source' => $source,
            'medium' => $touch['medium'] ?? null,
            'campaign' => $touch['campaign'] ?? null,
            'referral_code' => $touch['referral_code'] ?? null,
            'partner_code' => $touch['partner_code'] ?? null,
            'landing_path' => $touch['landing_path'] ?? null,
            'referrer_host' => $touch['referrer_host'] ?? null,
            'seen_at' => $seenAt,
        ];
    }

    /** @param array<string, mixed> $touch
     * @return array<string, mixed>
     */
    private function attributes(string $prefix, array $touch): array
    {
        $source = $touch['source'] instanceof AcquisitionSource
            ? $touch['source']->value
            : AcquisitionSource::Unknown->value;
        $attributes = [
            "{$prefix}_source" => $source,
            "{$prefix}_medium" => $touch['medium'],
            "{$prefix}_campaign" => $touch['campaign'],
            "{$prefix}_referral_code" => $touch['referral_code'],
            "{$prefix}_partner_code" => $touch['partner_code'],
            "{$prefix}_landing_path" => $touch['landing_path'],
            "{$prefix}_referrer_host" => $touch['referrer_host'],
        ];

        if ($prefix !== 'attributed') {
            $attributes["{$prefix}_seen_at"] = $touch['seen_at'];
        }

        return $attributes;
    }

    private function matchingSlot(
        Promotion $promotion,
        Booking $booking,
        BookingWindow $window,
    ): ?PromotionSlot {
        if (! $promotion->targets_specific_slots) {
            return null;
        }

        $promotion->loadMissing('slots');

        return $promotion->slots->first(
            fn (PromotionSlot $slot) => $slot->resource_id === $booking->resource_id
                && $slot->slot_date->toDateString() === $window->localStart->toDateString()
                && $slot->starts_at_time <= $window->localStart->format('H:i:s')
                && $slot->ends_at_time >= $window->localEnd->format('H:i:s'),
        );
    }

    /** @param array<string, mixed>|null $context */
    private function reactivationCampaign(Booking $booking, ?array $context): ?ReactivationCampaign
    {
        $token = $context['reactivation_campaign_token'] ?? null;

        if (! is_string($token) || $token === '' || $booking->player_user_id === null) {
            return null;
        }

        return ReactivationCampaign::query()
            ->where('campaign_token', $token)
            ->where('organization_id', $booking->organization_id)
            ->whereHas('recipients', fn ($query) => $query
                ->where('user_id', $booking->player_user_id)
                ->whereNotNull('clicked_at'))
            ->first();
    }
}
