<?php

namespace Database\Seeders;

use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\BookingAttribution;
use App\Models\CourtResource;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnalyticsSeeder extends Seeder
{
    use WithoutModelEvents;

    private const VENUE_SLUG = 'demo-courts-makati';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo analytics may only be seeded in local or testing environments.');
        }

        $venue = Venue::query()
            ->with('organization')
            ->where('slug', self::VENUE_SLUG)
            ->first();

        if ($venue === null) {
            throw new RuntimeException('Create the demo-courts-makati venue before running AnalyticsSeeder.');
        }

        $resources = $venue->resources()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($resources->isEmpty()) {
            throw new RuntimeException('The demo-courts-makati venue needs at least one active court before analytics can be seeded.');
        }

        $promotion = $venue->promotions()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        $now = CarbonImmutable::now($venue->organization->timezone);
        $monthKey = $now->format('Ym');

        DB::transaction(function () use ($venue, $resources, $promotion, $now, $monthKey): void {
            $players = $this->players();

            foreach ($this->bookingSources() as $index => $sourceData) {
                $resource = $resources[$index % $resources->count()];
                $player = $players[$index % $players->count()];
                $createdAt = $this->createdAt($now, $index);
                $bookingPromotion = $sourceData['source'] === AcquisitionSource::MarketplacePromotion
                    ? $promotion
                    : null;
                $booking = $this->booking(
                    $venue,
                    $resource,
                    $player,
                    $bookingPromotion,
                    $sourceData,
                    $createdAt,
                    $monthKey,
                    $index,
                );

                $this->attribution($booking, $bookingPromotion, $sourceData, $createdAt);
                $this->bookingEvents(
                    $booking,
                    $bookingPromotion,
                    $sourceData,
                    $createdAt,
                    $monthKey,
                    $index,
                );
            }

            $this->browsingEvents($venue, $resources, $now, $monthKey);
        });

        $this->command?->info("Seeded current-month analytics for {$venue->name}.");
    }

    /** @return Collection<int, User> */
    private function players(): Collection
    {
        return collect([
            'Alex Reyes',
            'Bianca Santos',
            'Carlo Mendoza',
            'Dana Cruz',
            'Enzo Garcia',
            'Faith Lim',
            'Gio Navarro',
            'Hazel Flores',
        ])->map(function (string $name, int $index): User {
            return User::query()->firstOrCreate(
                ['email' => sprintf('analytics.player%02d@example.com', $index + 1)],
                [
                    'name' => $name,
                    'email_verified_at' => now(),
                    'password' => 'password',
                    'is_platform_admin' => false,
                ],
            );
        });
    }

    /** @return array<int, array{source: AcquisitionSource, evidence: string, medium: ?string, campaign: ?string, detail: ?string, referrer_host: ?string, referral_code: ?string}> */
    private function bookingSources(): array
    {
        return [
            $this->source(AcquisitionSource::MarketplaceOrganic, 'trusted_link', 'marketplace', 'court_search', 'FinACourt court search'),
            $this->source(AcquisitionSource::MarketplaceOrganic, 'trusted_link', 'marketplace', 'nearby_courts', 'FinACourt nearby courts'),
            $this->source(AcquisitionSource::MarketplaceOrganic, 'trusted_link', 'marketplace', 'sport_search', 'FinACourt sport search'),
            $this->source(AcquisitionSource::MarketplacePromotion, 'server_promotion', 'promotion', null, 'FinACourt deal'),
            $this->source(AcquisitionSource::Facebook, 'utm', 'social', 'weekend-games', 'Facebook weekend post', 'facebook.com'),
            $this->source(AcquisitionSource::Instagram, 'utm', 'social', 'court-highlights', 'Instagram court highlights', 'instagram.com'),
            $this->source(AcquisitionSource::GoogleOrganic, 'referrer', 'organic', null, 'Google Search', 'google.com'),
            $this->source(AcquisitionSource::GoogleMaps, 'trusted_link', 'maps', 'google-business-profile', 'Google Maps'),
            $this->source(AcquisitionSource::SharedLink, 'trusted_link', 'player_share', 'venue-share', 'Player shared venue link'),
            $this->source(AcquisitionSource::QrCode, 'trusted_link', 'qr', 'QR-DEMO-COURTS', 'Front desk QR code'),
            $this->source(AcquisitionSource::Referral, 'browser_marker', 'referral', 'PLAYER-REFERRAL', 'Player referral', null, 'PLAYER-REFERRAL'),
            $this->source(AcquisitionSource::Direct, 'fallback', null, null, null),
        ];
    }

    /** @return array{source: AcquisitionSource, evidence: string, medium: ?string, campaign: ?string, detail: ?string, referrer_host: ?string, referral_code: ?string} */
    private function source(
        AcquisitionSource $source,
        string $evidence,
        ?string $medium,
        ?string $campaign,
        ?string $detail,
        ?string $referrerHost = null,
        ?string $referralCode = null,
    ): array {
        return [
            'source' => $source,
            'evidence' => $evidence,
            'medium' => $medium,
            'campaign' => $campaign,
            'detail' => $detail,
            'referrer_host' => $referrerHost,
            'referral_code' => $referralCode,
        ];
    }

    private function createdAt(CarbonImmutable $now, int $index): CarbonImmutable
    {
        $monthStart = $now->startOfMonth();
        $candidate = $monthStart
            ->addDays($index % max(1, $now->day))
            ->setTime(8 + ($index % 10), 15);
        $latest = $now->subMinutes(15 + $index);

        if ($candidate->greaterThan($latest)) {
            $candidate = $latest;
        }

        return $candidate->lessThan($monthStart)
            ? $monthStart->addMinute()
            : $candidate;
    }

    /**
     * @param  array{source: AcquisitionSource, evidence: string, medium: ?string, campaign: ?string, detail: ?string, referrer_host: ?string, referral_code: ?string}  $sourceData
     */
    private function booking(
        Venue $venue,
        CourtResource $resource,
        User $player,
        ?Promotion $promotion,
        array $sourceData,
        CarbonImmutable $createdAt,
        string $monthKey,
        int $index,
    ): Booking {
        $reference = sprintf('BK-AN-DEMO-%s-%02d', $monthKey, $index + 1);
        $existing = Booking::query()->where('reference', $reference)->first();

        if ($existing !== null) {
            if ($existing->organization_id !== $venue->organization_id || $existing->venue_id !== $venue->getKey()) {
                throw new RuntimeException("Analytics booking reference {$reference} belongs to another venue.");
            }

            return $existing;
        }

        $originalPrice = (float) $resource->base_hourly_rate;
        $unitPrice = $promotion === null ? $originalPrice : round($originalPrice * 0.8, 2);
        $startAt = $createdAt->addHours(2)->startOfHour();
        $booking = new Booking;
        $booking->fill([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource->getKey(),
            'promotion_id' => $promotion?->getKey(),
            'promotion_campaign_token' => $promotion?->campaign_token,
            'promotion_title' => $promotion?->title,
            'player_user_id' => $player->getKey(),
            'reference' => $reference,
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Marketplace,
            'traffic_source' => $sourceData['source']->value,
            'traffic_source_detail' => $promotion?->campaign_token ?? $sourceData['detail'],
            'customer_name' => $player->name,
            'customer_email' => $player->email,
            'notes' => 'Synthetic local activity generated by AnalyticsSeeder.',
            'start_at' => $startAt->utc(),
            'end_at' => $startAt->addHour()->utc(),
            'timezone' => $venue->organization->timezone,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'original_unit_price' => number_format($originalPrice, 2, '.', ''),
            'total_amount' => number_format($unitPrice, 2, '.', ''),
            'original_total_amount' => number_format($originalPrice, 2, '.', ''),
            'discount_amount' => number_format($originalPrice - $unitPrice, 2, '.', ''),
            'platform_service_fee_amount' => '0.00',
            'player_total_amount' => number_format($unitPrice, 2, '.', ''),
            'currency' => $resource->currency,
            'created_by_user_id' => $player->getKey(),
        ]);
        $booking->forceFill([
            'created_at' => $createdAt->utc(),
            'updated_at' => $createdAt->utc(),
        ])->save();

        return $booking;
    }

    /**
     * @param  array{source: AcquisitionSource, evidence: string, medium: ?string, campaign: ?string, detail: ?string, referrer_host: ?string, referral_code: ?string}  $sourceData
     */
    private function attribution(
        Booking $booking,
        ?Promotion $promotion,
        array $sourceData,
        CarbonImmutable $createdAt,
    ): void {
        if ($booking->attribution()->exists()) {
            return;
        }

        $campaign = $promotion?->campaign_token ?? $sourceData['campaign'];
        $touch = [
            'source' => $sourceData['source'],
            'evidence' => $sourceData['evidence'],
            'medium' => $sourceData['medium'],
            'campaign' => $campaign,
            'referral_code' => $sourceData['referral_code'],
            'landing_path' => '/venues/'.self::VENUE_SLUG,
            'referrer_host' => $sourceData['referrer_host'],
        ];

        BookingAttribution::query()->create([
            'booking_id' => $booking->getKey(),
            'organization_id' => $booking->organization_id,
            'venue_id' => $booking->venue_id,
            'first_source' => $touch['source'],
            'first_evidence' => $touch['evidence'],
            'first_medium' => $touch['medium'],
            'first_campaign' => $touch['campaign'],
            'first_referral_code' => $touch['referral_code'],
            'first_landing_path' => $touch['landing_path'],
            'first_referrer_host' => $touch['referrer_host'],
            'first_seen_at' => $createdAt->subMinutes(30)->utc(),
            'last_source' => $touch['source'],
            'last_evidence' => $touch['evidence'],
            'last_medium' => $touch['medium'],
            'last_campaign' => $touch['campaign'],
            'last_referral_code' => $touch['referral_code'],
            'last_landing_path' => $touch['landing_path'],
            'last_referrer_host' => $touch['referrer_host'],
            'last_seen_at' => $createdAt->subMinutes(5)->utc(),
            'attributed_source' => $touch['source'],
            'attributed_evidence' => $touch['evidence'],
            'attributed_medium' => $touch['medium'],
            'attributed_campaign' => $touch['campaign'],
            'attributed_referral_code' => $touch['referral_code'],
            'attributed_landing_path' => $touch['landing_path'],
            'attributed_referrer_host' => $touch['referrer_host'],
            'attributed_at' => $createdAt->utc(),
            'promotion_id' => $promotion?->getKey(),
            'promotion_campaign_token' => $promotion?->campaign_token,
            'promotion_title' => $promotion?->title,
            'rule_version' => 'analytics_demo_seed_v1',
        ]);
    }

    /**
     * @param  array{source: AcquisitionSource, evidence: string, medium: ?string, campaign: ?string, detail: ?string, referrer_host: ?string, referral_code: ?string}  $sourceData
     */
    private function bookingEvents(
        Booking $booking,
        ?Promotion $promotion,
        array $sourceData,
        CarbonImmutable $createdAt,
        string $monthKey,
        int $index,
    ): void {
        $visitorHash = hash('sha256', "analytics-demo-booker|{$monthKey}|{$index}");
        $events = [
            [AnalyticsEventType::VenueImpression, -40],
            [AnalyticsEventType::VenueProfileView, -30],
            [AnalyticsEventType::AvailabilityView, -20],
            [AnalyticsEventType::BookingStart, -10],
            [AnalyticsEventType::CompletedBooking, 0],
        ];

        if ($promotion !== null) {
            array_splice($events, 2, 0, [
                [AnalyticsEventType::PromotionImpression, -25],
                [AnalyticsEventType::PromotionClick, -22],
            ]);
        }

        foreach ($events as [$eventType, $minuteOffset]) {
            $this->event(
                $booking->venue,
                $booking->resource,
                $promotion,
                in_array($eventType, [AnalyticsEventType::BookingStart, AnalyticsEventType::CompletedBooking], true) ? $booking : null,
                $eventType,
                $sourceData['source'],
                $promotion?->campaign_token ?? $sourceData['detail'],
                $eventType === AnalyticsEventType::CompletedBooking ? null : $visitorHash,
                $createdAt->addMinutes($minuteOffset),
                "booking|{$monthKey}|{$index}|{$eventType->value}",
            );
        }
    }

    /** @param Collection<int, CourtResource> $resources */
    private function browsingEvents(
        Venue $venue,
        Collection $resources,
        CarbonImmutable $now,
        string $monthKey,
    ): void {
        foreach (range(0, 11) as $index) {
            $resource = $resources[$index % $resources->count()];
            $occurredAt = $this->createdAt($now, $index)->subHour();
            $visitorHash = hash('sha256', "analytics-demo-browser|{$monthKey}|{$index}");
            $source = $index % 2 === 0 ? AcquisitionSource::GoogleOrganic : AcquisitionSource::Facebook;
            $detail = $source === AcquisitionSource::GoogleOrganic ? 'Google Search' : 'Facebook venue post';

            $this->event($venue, null, null, null, AnalyticsEventType::VenueImpression, $source, $detail, $visitorHash, $occurredAt, "browser|{$monthKey}|{$index}|impression");
            $this->event($venue, null, null, null, AnalyticsEventType::VenueProfileView, $source, $detail, $visitorHash, $occurredAt->addMinutes(5), "browser|{$monthKey}|{$index}|profile");

            if ($index % 2 === 0) {
                $this->event($venue, $resource, null, null, AnalyticsEventType::AvailabilityView, $source, $detail, $visitorHash, $occurredAt->addMinutes(10), "browser|{$monthKey}|{$index}|availability");
            }
        }
    }

    private function event(
        Venue $venue,
        ?CourtResource $resource,
        ?Promotion $promotion,
        ?Booking $booking,
        AnalyticsEventType $eventType,
        AcquisitionSource $source,
        ?string $sourceDetail,
        ?string $visitorHash,
        CarbonImmutable $occurredAt,
        string $key,
    ): void {
        AnalyticsEvent::query()->updateOrCreate(
            ['dedupe_key' => hash('sha256', 'analytics-demo|'.$key)],
            [
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'resource_id' => $resource?->getKey(),
                'promotion_id' => $promotion?->getKey(),
                'booking_id' => $booking?->getKey(),
                'event_type' => $eventType,
                'visitor_hash' => $visitorHash,
                'traffic_source' => $source->value,
                'source_detail' => $sourceDetail,
                'entry_context' => 'local_demo',
                'is_demo' => true,
                'metadata' => [
                    'schema_version' => 2,
                    'local_demo' => true,
                    'generated_by' => self::class,
                ],
                'occurred_at' => $occurredAt->utc(),
            ],
        );
    }
}
