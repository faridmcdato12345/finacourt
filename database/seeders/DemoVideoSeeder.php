<?php

namespace Database\Seeders;

use App\BookingLinks\ExternalBookingDestinationManager;
use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\OrganizationPermission;
use App\Enums\OwnerPayoutMethod;
use App\Enums\OwnerSettlementEntryType;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\RefundRequestStatus;
use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Enums\Weekday;
use App\Models\Amenity;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\BookingAttribution;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutProfile;
use App\Models\OwnerSettlementEntry;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\RefundRequest;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VisibilityLink;
use App\Settlements\OwnerSettlementLedger;
use App\Visibility\VisibilityLinkManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoVideoSeeder extends Seeder
{
    use WithoutModelEvents;

    public const VENUE_SLUG = 'finacourt-video-demo-makati';

    private const ORGANIZATION_SLUG = 'finacourt-demo-video';

    public function run(): void
    {
        $allowed = in_array(app()->environment(), config('demo-video.allowed_environments', []), true);

        if (! $allowed && ! config('demo-video.allow_other_environments')) {
            throw new RuntimeException('Demo-video data may only be seeded in local, testing, or staging environments.');
        }

        $this->call([SportSeeder::class, AmenitySeeder::class]);

        DB::transaction(function (): void {
            $organization = Organization::query()->updateOrCreate(
                ['slug' => self::ORGANIZATION_SLUG],
                [
                    'name' => 'FinACourt Demo Video',
                    'timezone' => 'Asia/Manila',
                    'requires_venue_claim_approval' => false,
                ],
            );
            $owner = $this->user((string) config('demo-video.owner_email'), 'Demo Court Owner');
            $player = $this->user((string) config('demo-video.player_email'), 'Demo Player');

            Membership::query()->updateOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'user_id' => $owner->getKey(),
                ],
                [
                    'role' => MembershipRole::Owner,
                    'permissions' => [],
                    'joined_at' => now(),
                ],
            );
            $staff = $this->user('demo.video.staff@finacourt.test', 'Jamie Court Staff');
            Membership::query()->updateOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'user_id' => $staff->getKey(),
                ],
                [
                    'role' => MembershipRole::Staff,
                    'permissions' => [
                        OrganizationPermission::ManageBookings->value,
                        OrganizationPermission::ManageInventory->value,
                    ],
                    'joined_at' => now()->subDays(18),
                    'suspended_at' => null,
                    'suspended_by_user_id' => null,
                    'removed_at' => null,
                    'removed_by_user_id' => null,
                ],
            );

            $venue = Venue::query()->updateOrCreate(
                ['slug' => self::VENUE_SLUG],
                [
                    'organization_id' => $organization->getKey(),
                    'name' => 'FinACourt Demo Courts Makati',
                    'description' => 'A clearly labeled demonstration venue with bookable courts, live schedules, and private owner analytics.',
                    'address' => '100 Demo Sports Avenue, Barangay San Lorenzo',
                    'city' => 'Makati City',
                    'city_slug' => 'makati',
                    'province' => 'Metro Manila',
                    'province_slug' => 'metro-manila',
                    'latitude' => '14.5537000',
                    'longitude' => '121.0244000',
                    'coordinates_source' => 'demo_video_seed',
                    'coordinates_verified_at' => now(),
                    'phone' => '+63 900 000 0000',
                    'email' => 'demo.venue@finacourt.test',
                    'is_published' => true,
                    'claimed_at' => now(),
                    'verified_at' => now(),
                    'loyalty_active' => true,
                    'loyalty_terms_version' => 1,
                    'loyalty_stamps_required' => 5,
                    'loyalty_discount_percent' => '10.00',
                    'loyalty_discount_cap' => '100.00',
                ],
            );

            $sports = Sport::query()
                ->whereIn('slug', ['pickleball', 'badminton', 'basketball'])
                ->get()
                ->keyBy('slug');
            $venue->sports()->sync($sports->pluck('id'));
            $venue->amenities()->sync(Amenity::query()
                ->whereIn('slug', ['parking', 'restrooms', 'showers', 'water-station'])
                ->pluck('id'));

            foreach (Weekday::cases() as $day) {
                OperatingHour::query()->updateOrCreate(
                    ['venue_id' => $venue->getKey(), 'day_of_week' => $day->value],
                    ['is_closed' => false, 'opens_at' => '06:00', 'closes_at' => '00:00'],
                );
            }

            $resources = collect([
                ['name' => 'Pickleball Court 1', 'sport' => 'pickleball', 'setting' => ResourceSetting::Covered, 'rate' => '650.00'],
                ['name' => 'Badminton Court 1', 'sport' => 'badminton', 'setting' => ResourceSetting::Indoor, 'rate' => '550.00'],
                ['name' => 'Basketball Court 1', 'sport' => 'basketball', 'setting' => ResourceSetting::Indoor, 'rate' => '1200.00'],
            ])->map(function (array $definition) use ($venue, $sports): CourtResource {
                $sport = $sports->get($definition['sport']);

                if (! $sport instanceof Sport) {
                    throw new RuntimeException("Missing seeded sport {$definition['sport']}.");
                }

                return CourtResource::query()->updateOrCreate(
                    ['venue_id' => $venue->getKey(), 'name' => $definition['name']],
                    [
                        'sport_id' => $sport->getKey(),
                        'resource_type' => ResourceType::Court,
                        'setting' => $definition['setting'],
                        'is_active' => true,
                        'base_hourly_rate' => $definition['rate'],
                        'currency' => 'PHP',
                        'booking_increment_minutes' => 60,
                    ],
                );
            })->values();

            $this->clearPreviousRecordedBooking($venue, $player);
            $promotion = $this->seedPromotion($venue);
            $this->seedAnalytics($venue, $resources, $promotion);
            $this->seedPlayerWorkflow($venue, $resources->firstOrFail(), $player, $owner);
            $this->seedOwnerEarnings($organization, $owner);
            $this->seedExternalBookingLinks($venue, $owner);
        });

        $this->command?->info('FinACourt demo-video venue, accounts, bookings, analytics, earnings, and Booking Links seeded.');
    }

    private function user(string $email, string $name): User
    {
        if (! str_ends_with(strtolower($email), '.test')) {
            throw new RuntimeException('Demo-video accounts must use the reserved .test domain.');
        }

        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => (string) config('demo-video.account_password'),
                'is_platform_admin' => false,
            ],
        );
    }

    private function clearPreviousRecordedBooking(Venue $venue, User $player): void
    {
        // Browser walkthroughs intentionally exercise real analytics routes.
        // Clear only non-demo events on this dedicated demo venue so every
        // recording begins from the same deterministic metrics.
        DB::table('analytics_events')
            ->where('venue_id', $venue->getKey())
            ->where('is_demo', false)
            ->delete();

        $bookingIds = Booking::query()
            ->where('venue_id', $venue->getKey())
            ->where('player_user_id', $player->getKey())
            ->pluck('id');

        if ($bookingIds->isEmpty()) {
            return;
        }

        DB::table('analytics_events')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('booking_attributions')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('loyalty_stamps')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('loyalty_redemptions')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('refund_requests')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('payment_transitions')->whereIn(
            'payment_id',
            DB::table('payments')->whereIn('booking_id', $bookingIds)->select('id'),
        )->delete();
        DB::table('payments')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('bookings')->whereIn('id', $bookingIds)->delete();
    }

    private function seedPlayerWorkflow(Venue $venue, CourtResource $resource, User $player, User $owner): void
    {
        $now = CarbonImmutable::now($venue->organization->timezone);

        foreach ([8, 5, 2] as $index => $daysAgo) {
            $startAt = $now->subDays($daysAgo)->setTime(18, 0);
            $reference = sprintf('BK-VIDEO-LOYALTY-%02d', $index + 1);
            $booking = Booking::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'organization_id' => $venue->organization_id,
                    'venue_id' => $venue->getKey(),
                    'resource_id' => $resource->getKey(),
                    'player_user_id' => $player->getKey(),
                    'status' => BookingStatus::Confirmed,
                    'source' => BookingSource::Marketplace,
                    'customer_name' => $player->name,
                    'customer_email' => $player->email,
                    'notes' => 'Synthetic completed game for the full product demo.',
                    'start_at' => $startAt->utc(),
                    'end_at' => $startAt->addHour()->utc(),
                    'timezone' => $venue->organization->timezone,
                    'unit_price' => $resource->base_hourly_rate,
                    'original_unit_price' => $resource->base_hourly_rate,
                    'total_amount' => $resource->base_hourly_rate,
                    'original_total_amount' => $resource->base_hourly_rate,
                    'discount_amount' => '0.00',
                    'platform_service_fee_amount' => '25.00',
                    'player_total_amount' => number_format((float) $resource->base_hourly_rate + 25, 2, '.', ''),
                    'currency' => 'PHP',
                    'payment_mode' => PaymentMode::HostedCheckout,
                    'payment_status' => PaymentStatus::Paid,
                    'created_by_user_id' => $player->getKey(),
                    'loyalty_eligible' => true,
                    'loyalty_processed_at' => $startAt->addHours(2)->utc(),
                    'loyalty_terms_version' => $venue->loyalty_terms_version,
                    'loyalty_stamps_required' => $venue->loyalty_stamps_required,
                    'loyalty_discount_percent' => $venue->loyalty_discount_percent,
                    'loyalty_discount_cap' => $venue->loyalty_discount_cap,
                ],
            );
            Payment::query()->updateOrCreate(
                ['reference' => sprintf('PAY-VIDEO-LOYALTY-%02d', $index + 1)],
                [
                    'organization_id' => $venue->organization_id,
                    'booking_id' => $booking->getKey(),
                    'provider' => 'demo_video',
                    'mode' => PaymentMode::HostedCheckout,
                    'status' => PaymentStatus::Paid,
                    'amount' => $booking->player_total_amount,
                    'venue_amount' => $booking->total_amount,
                    'platform_service_fee_amount' => $booking->platform_service_fee_amount,
                    'refunded_amount' => '0.00',
                    'currency' => 'PHP',
                    'provider_reference' => sprintf('demo-loyalty-session-%02d', $index + 1),
                    'provider_payment_reference' => sprintf('pay_demo_loyalty_%02d', $index + 1),
                    'requires_review' => false,
                    'paid_at' => $startAt->subDay()->utc(),
                    'created_by_user_id' => $player->getKey(),
                    'verified_by_user_id' => $owner->getKey(),
                ],
            );
            DB::table('loyalty_stamps')->updateOrInsert(
                ['booking_id' => $booking->getKey()],
                [
                    'venue_id' => $venue->getKey(),
                    'player_user_id' => $player->getKey(),
                    'local_play_date' => $startAt->toDateString(),
                    'terms_version' => $venue->loyalty_terms_version,
                    'stamps_required' => $venue->loyalty_stamps_required,
                    'discount_percent' => $venue->loyalty_discount_percent,
                    'discount_cap' => $venue->loyalty_discount_cap,
                    'reversed_at' => null,
                    'created_at' => $startAt->addHours(2)->utc(),
                    'updated_at' => $startAt->addHours(2)->utc(),
                ],
            );
        }

        $startAt = $now->addDays(4)->setTime(19, 0);
        $booking = Booking::query()->updateOrCreate(
            ['reference' => 'BK-VIDEO-REFUND-DEMO'],
            [
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'resource_id' => $resource->getKey(),
                'player_user_id' => $player->getKey(),
                'status' => BookingStatus::Confirmed,
                'source' => BookingSource::Marketplace,
                'customer_name' => $player->name,
                'customer_email' => $player->email,
                'notes' => 'Synthetic paid booking with a pending refund request for the full product demo.',
                'start_at' => $startAt->utc(),
                'end_at' => $startAt->addHour()->utc(),
                'timezone' => $venue->organization->timezone,
                'unit_price' => $resource->base_hourly_rate,
                'original_unit_price' => $resource->base_hourly_rate,
                'total_amount' => $resource->base_hourly_rate,
                'original_total_amount' => $resource->base_hourly_rate,
                'discount_amount' => '0.00',
                'platform_service_fee_amount' => '25.00',
                'player_total_amount' => number_format((float) $resource->base_hourly_rate + 25, 2, '.', ''),
                'currency' => 'PHP',
                'payment_mode' => PaymentMode::HostedCheckout,
                'payment_status' => PaymentStatus::Paid,
                'created_by_user_id' => $player->getKey(),
                'loyalty_eligible' => true,
                'loyalty_terms_version' => $venue->loyalty_terms_version,
                'loyalty_stamps_required' => $venue->loyalty_stamps_required,
                'loyalty_discount_percent' => $venue->loyalty_discount_percent,
                'loyalty_discount_cap' => $venue->loyalty_discount_cap,
            ],
        );
        $payment = Payment::query()->updateOrCreate(
            ['reference' => 'PAY-VIDEO-REFUND-DEMO'],
            [
                'organization_id' => $venue->organization_id,
                'booking_id' => $booking->getKey(),
                'provider' => 'demo_video',
                'mode' => PaymentMode::HostedCheckout,
                'status' => PaymentStatus::Paid,
                'amount' => $booking->player_total_amount,
                'venue_amount' => $booking->total_amount,
                'platform_service_fee_amount' => $booking->platform_service_fee_amount,
                'refunded_amount' => '0.00',
                'currency' => 'PHP',
                'provider_reference' => 'demo-refund-session',
                'provider_payment_reference' => 'pay_demo_refund',
                'requires_review' => false,
                'paid_at' => $now->subHour()->utc(),
                'created_by_user_id' => $player->getKey(),
                'verified_by_user_id' => $owner->getKey(),
            ],
        );
        RefundRequest::query()->updateOrCreate(
            ['reference' => 'RFD-VIDEO-PENDING'],
            [
                'organization_id' => $venue->organization_id,
                'booking_id' => $booking->getKey(),
                'payment_id' => $payment->getKey(),
                'status' => RefundRequestStatus::Requested,
                'amount' => $payment->amount,
                'currency' => 'PHP',
                'reason' => 'My schedule changed and I can no longer attend this game.',
                'requested_by_user_id' => $player->getKey(),
                'provider' => 'demo_video',
                'provider_payment_reference' => $payment->provider_payment_reference,
                'attempts' => 0,
                'requires_review' => false,
                'requested_at' => $now->subMinutes(20)->utc(),
            ],
        );
    }

    private function seedOwnerEarnings(Organization $organization, User $owner): void
    {
        // This organization exists only for local/staging product-video work.
        // Keep its earnings page deterministic and free of incidental checkout
        // tests without changing the real settlement or payout workflows.
        OwnerSettlementEntry::query()
            ->where('organization_id', $organization->getKey())
            ->delete();
        OwnerPayout::query()
            ->where('organization_id', $organization->getKey())
            ->delete();

        OwnerPayoutProfile::query()->updateOrCreate(
            ['organization_id' => $organization->getKey()],
            [
                'method' => OwnerPayoutMethod::Gcash,
                'account_name' => 'FinACourt Demo Venue',
                'details' => ['mobile_number' => '09000000000'],
                'is_active' => true,
                'updated_by_user_id' => $owner->getKey(),
            ],
        );

        OwnerSettlementEntry::query()->updateOrCreate(
            ['source_key' => 'demo-video:available-owner-earnings'],
            [
                'organization_id' => $organization->getKey(),
                'payment_id' => null,
                'booking_id' => null,
                'owner_payout_id' => null,
                'type' => OwnerSettlementEntryType::AdminAdjustment,
                'amount' => '8750.00',
                'currency' => 'PHP',
                'description' => 'Synthetic available balance for the FinACourt product video.',
                'occurred_at' => now()->subDays(4),
                'available_at' => now()->subDays(3),
                'metadata' => ['is_demo' => true, 'purpose' => 'owner_earnings_video'],
                'created_by_user_id' => $owner->getKey(),
            ],
        );

        $futureBookings = Booking::query()
            ->where('organization_id', $organization->getKey())
            ->where('reference', 'like', 'BK-VIDEO-%')
            ->where('start_at', '>', now())
            ->orderBy('start_at')
            ->limit(2)
            ->get();

        $ledger = app(OwnerSettlementLedger::class);

        foreach ($futureBookings as $index => $booking) {
            $serviceFee = '25.00';
            $playerTotal = number_format((float) $booking->total_amount + (float) $serviceFee, 2, '.', '');
            $booking->update([
                'payment_mode' => PaymentMode::HostedCheckout,
                'payment_status' => PaymentStatus::Paid,
                'platform_service_fee_amount' => $serviceFee,
                'player_total_amount' => $playerTotal,
            ]);

            $payment = Payment::query()->updateOrCreate(
                ['reference' => sprintf('PAY-VIDEO-EARNINGS-PENDING-%02d', $index + 1)],
                [
                    'organization_id' => $organization->getKey(),
                    'booking_id' => $booking->getKey(),
                    'provider' => 'demo_video',
                    'mode' => PaymentMode::HostedCheckout,
                    'status' => PaymentStatus::Paid,
                    'amount' => $playerTotal,
                    'venue_amount' => $booking->total_amount,
                    'platform_service_fee_amount' => $serviceFee,
                    'refunded_amount' => '0.00',
                    'currency' => 'PHP',
                    'provider_reference' => sprintf('demo-video-earnings-%02d', $index + 1),
                    'provider_payment_reference' => sprintf('pay_demo_video_%02d', $index + 1),
                    'requires_review' => false,
                    'review_reason' => null,
                    'paid_at' => now()->subHour(),
                    'failed_at' => null,
                    'cancelled_at' => null,
                    'refunded_at' => null,
                    'created_by_user_id' => $booking->player_user_id,
                    'verified_by_user_id' => $owner->getKey(),
                ],
            );

            $ledger->recordPaidPayment($payment->fresh(['booking']));
        }
    }

    private function seedPromotion(Venue $venue): Promotion
    {
        $now = CarbonImmutable::now($venue->organization->timezone);
        $campaignToken = 'DEMO-FILL-SLOW-HOURS-15';
        $promotion = Promotion::query()->firstOrNew(['campaign_token' => $campaignToken]);

        if ($promotion->exists && ($promotion->venue_id !== $venue->getKey()
            || $promotion->organization_id !== $venue->organization_id)) {
            throw new RuntimeException("Demo-video promotion token {$campaignToken} belongs to another venue.");
        }

        $promotion->fill([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'resource_id' => null,
            'audience_sport_id' => null,
            'audience_city_slug' => $venue->city_slug,
            'title' => 'Weekday Afternoon Court Deal',
            'description' => 'A demonstration offer for quieter weekday court hours.',
            'promotion_type' => PromotionType::TimeWindow,
            'goal' => PromotionGoal::IncreaseOffPeakBookings,
            'status' => PromotionStatus::Active,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => '15.00',
            'starts_on' => $now->toDateString(),
            'ends_on' => $now->addMonth()->toDateString(),
            'targets_specific_slots' => false,
            'days_of_week' => [
                Weekday::Monday->value,
                Weekday::Tuesday->value,
                Weekday::Wednesday->value,
                Weekday::Thursday->value,
                Weekday::Friday->value,
            ],
            'starts_at_time' => '14:00',
            'ends_at_time' => '17:00',
            'is_active' => true,
            'is_public' => true,
            'impressions_count' => 286,
            'clicks_count' => 74,
            'booking_starts_count' => 21,
        ]);
        $promotion->save();
        $promotion->slots()->delete();

        return $promotion;
    }

    /** @param Collection<int, CourtResource> $resources */
    private function seedAnalytics(Venue $venue, Collection $resources, Promotion $promotion): void
    {
        $now = CarbonImmutable::now($venue->organization->timezone);
        $monthKey = $now->format('Ym');
        $sources = [
            $this->source(AcquisitionSource::MarketplaceOrganic, 'marketplace', 'court-search', 'FinACourt search'),
            $this->source(AcquisitionSource::MarketplaceOrganic, 'marketplace', 'nearby-courts', 'Nearby courts'),
            $this->source(AcquisitionSource::MarketplaceOrganic, 'marketplace', 'pickleball-makati', 'FinACourt search'),
            $this->source(AcquisitionSource::GoogleOrganic, 'organic', null, 'Google Search', 'google.com'),
            $this->source(AcquisitionSource::GoogleOrganic, 'organic', null, 'Google Search', 'google.com'),
            $this->source(AcquisitionSource::GoogleMaps, 'maps', 'demo-maps', 'Google Maps'),
            $this->source(AcquisitionSource::Facebook, 'social', 'weekend-games', 'Facebook'),
            $this->source(AcquisitionSource::Facebook, 'social', 'weekend-games', 'Facebook'),
            $this->source(AcquisitionSource::Instagram, 'social', 'court-highlights', 'Instagram'),
            $this->source(AcquisitionSource::QrCode, 'qr', 'front-desk-demo', 'Front desk QR'),
            $this->source(AcquisitionSource::SharedLink, 'player-share', 'venue-share', 'Shared link'),
            $this->source(AcquisitionSource::Direct, null, null, 'Direct'),
        ];
        $players = collect(['Alex Reyes', 'Bianca Santos', 'Carlo Mendoza', 'Dana Cruz', 'Enzo Garcia', 'Faith Lim'])
            ->map(fn (string $name, int $index) => $this->user(
                sprintf('demo.video.analytics.player%02d@finacourt.test', $index + 1),
                $name,
            ));

        foreach ($sources as $index => $source) {
            $resource = $resources[$index % $resources->count()];
            $createdAt = $this->currentMonthMoment($now, $index);
            $startAt = $index < 3
                ? $now->startOfDay()->setTime(9 + ($index * 2), 0)
                : $now->addDays(2 + ($index % 5))->setTime(8 + ($index % 10), 0);
            $player = $players[$index % $players->count()];
            $reference = sprintf('BK-VIDEO-%s-%02d', $monthKey, $index + 1);
            $booking = Booking::query()->firstOrNew(['reference' => $reference]);
            $usesPromotion = in_array($index, [6, 7], true);
            $originalPrice = (float) $resource->base_hourly_rate;
            $discount = $usesPromotion ? $originalPrice * 0.15 : 0.0;
            $finalPrice = $originalPrice - $discount;

            if ($booking->exists && ($booking->venue_id !== $venue->getKey()
                || $booking->organization_id !== $venue->organization_id)) {
                throw new RuntimeException("Demo-video booking reference {$reference} belongs to another venue.");
            }

            $booking->fill([
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'resource_id' => $resource->getKey(),
                'promotion_id' => $usesPromotion ? $promotion->getKey() : null,
                'promotion_campaign_token' => $usesPromotion ? $promotion->campaign_token : null,
                'promotion_title' => $usesPromotion ? $promotion->title : null,
                'player_user_id' => $player->getKey(),
                'status' => BookingStatus::Confirmed,
                'source' => BookingSource::Marketplace,
                'traffic_source' => $source['source']->value,
                'traffic_source_detail' => $source['detail'],
                'customer_name' => $player->name,
                'customer_email' => $player->email,
                'notes' => 'Synthetic demo data generated for the FinACourt product video.',
                'start_at' => $startAt->utc(),
                'end_at' => $startAt->addHour()->utc(),
                'timezone' => $venue->organization->timezone,
                'unit_price' => number_format($finalPrice, 2, '.', ''),
                'original_unit_price' => $resource->base_hourly_rate,
                'total_amount' => number_format($finalPrice, 2, '.', ''),
                'original_total_amount' => $resource->base_hourly_rate,
                'discount_amount' => number_format($discount, 2, '.', ''),
                'platform_service_fee_amount' => '0.00',
                'player_total_amount' => number_format($finalPrice, 2, '.', ''),
                'currency' => 'PHP',
                'payment_mode' => PaymentMode::PayAtVenue,
                'payment_status' => PaymentStatus::Pending,
                'created_by_user_id' => $player->getKey(),
            ]);
            $booking->forceFill([
                'created_at' => $createdAt->utc(),
                'updated_at' => $createdAt->utc(),
            ])->save();

            $this->seedAttribution($booking, $source, $createdAt);
            $this->seedBookingEvents($booking, $source, $createdAt, $monthKey, $index);
        }

        foreach (range(0, 15) as $index) {
            $source = $index % 3 === 0 ? AcquisitionSource::Facebook : AcquisitionSource::GoogleOrganic;
            $occurredAt = $this->currentMonthMoment($now, $index)->subHour();
            $visitorHash = hash('sha256', "demo-video-browser|{$monthKey}|{$index}");

            $this->event($venue, null, null, AnalyticsEventType::VenueImpression, $source, $visitorHash, $occurredAt, "browser|{$monthKey}|{$index}|impression");
            $this->event($venue, null, null, AnalyticsEventType::VenueProfileView, $source, $visitorHash, $occurredAt->addMinutes(4), "browser|{$monthKey}|{$index}|profile");

            if ($index % 2 === 0) {
                $this->event($venue, $resources[$index % $resources->count()], null, AnalyticsEventType::AvailabilityView, $source, $visitorHash, $occurredAt->addMinutes(8), "browser|{$monthKey}|{$index}|availability");
            }
        }
    }

    /** @return array{source: AcquisitionSource, medium: ?string, campaign: ?string, detail: string, referrer_host: ?string} */
    private function source(
        AcquisitionSource $source,
        ?string $medium,
        ?string $campaign,
        string $detail,
        ?string $referrerHost = null,
    ): array {
        return compact('source', 'medium', 'campaign', 'detail') + ['referrer_host' => $referrerHost];
    }

    private function currentMonthMoment(CarbonImmutable $now, int $index): CarbonImmutable
    {
        $monthStart = $now->startOfMonth();
        $candidate = $monthStart
            ->addDays($index % max(1, $now->day))
            ->setTime(9 + ($index % 8), 15);
        $latest = $now->subMinutes(10 + $index);
        $moment = $candidate->greaterThan($latest) ? $latest : $candidate;

        return $moment->lessThan($monthStart) ? $monthStart->addMinute() : $moment;
    }

    /** @param array{source: AcquisitionSource, medium: ?string, campaign: ?string, detail: string, referrer_host: ?string} $source */
    private function seedAttribution(Booking $booking, array $source, CarbonImmutable $createdAt): void
    {
        if ($booking->attribution()->exists()) {
            return;
        }

        $landingPath = '/venues/'.self::VENUE_SLUG;

        BookingAttribution::query()->create([
            'booking_id' => $booking->getKey(),
            'organization_id' => $booking->organization_id,
            'venue_id' => $booking->venue_id,
            'first_source' => $source['source'],
            'first_evidence' => 'demo_seed',
            'first_medium' => $source['medium'],
            'first_campaign' => $source['campaign'],
            'first_landing_path' => $landingPath,
            'first_referrer_host' => $source['referrer_host'],
            'first_seen_at' => $createdAt->subMinutes(25)->utc(),
            'last_source' => $source['source'],
            'last_evidence' => 'demo_seed',
            'last_medium' => $source['medium'],
            'last_campaign' => $source['campaign'],
            'last_landing_path' => $landingPath,
            'last_referrer_host' => $source['referrer_host'],
            'last_seen_at' => $createdAt->subMinutes(5)->utc(),
            'attributed_source' => $source['source'],
            'attributed_evidence' => 'demo_seed',
            'attributed_medium' => $source['medium'],
            'attributed_campaign' => $source['campaign'],
            'attributed_landing_path' => $landingPath,
            'attributed_referrer_host' => $source['referrer_host'],
            'attributed_at' => $createdAt->utc(),
            'rule_version' => 'demo_video_seed_v1',
        ]);
    }

    /** @param array{source: AcquisitionSource, medium: ?string, campaign: ?string, detail: string, referrer_host: ?string} $source */
    private function seedBookingEvents(
        Booking $booking,
        array $source,
        CarbonImmutable $createdAt,
        string $monthKey,
        int $index,
    ): void {
        $visitorHash = hash('sha256', "demo-video-booker|{$monthKey}|{$index}");

        foreach ([
            [AnalyticsEventType::VenueImpression, -30],
            [AnalyticsEventType::VenueProfileView, -24],
            [AnalyticsEventType::AvailabilityView, -16],
            [AnalyticsEventType::BookingStart, -8],
            [AnalyticsEventType::CompletedBooking, 0],
        ] as [$eventType, $offset]) {
            $this->event(
                $booking->venue,
                $booking->resource,
                in_array($eventType, [AnalyticsEventType::BookingStart, AnalyticsEventType::CompletedBooking], true) ? $booking : null,
                $eventType,
                $source['source'],
                $eventType === AnalyticsEventType::CompletedBooking ? null : $visitorHash,
                $createdAt->addMinutes($offset),
                "booking|{$monthKey}|{$index}|{$eventType->value}",
            );
        }
    }

    private function event(
        Venue $venue,
        ?CourtResource $resource,
        ?Booking $booking,
        AnalyticsEventType $eventType,
        AcquisitionSource $source,
        ?string $visitorHash,
        CarbonImmutable $occurredAt,
        string $key,
        ?VisibilityLink $link = null,
    ): void {
        AnalyticsEvent::query()->updateOrCreate(
            ['dedupe_key' => hash('sha256', 'demo-video|'.$key)],
            [
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'resource_id' => $resource?->getKey(),
                'booking_id' => $booking?->getKey(),
                'visibility_link_id' => $link?->getKey(),
                'event_type' => $eventType,
                'entry_context' => 'demo_video',
                'is_demo' => true,
                'visitor_hash' => $visitorHash,
                'traffic_source' => $source->value,
                'source_detail' => $source->label(),
                'metadata' => [
                    'schema_version' => 2,
                    'demo_video' => true,
                    'generated_by' => self::class,
                ],
                'occurred_at' => $occurredAt->utc(),
            ],
        );
    }

    private function seedExternalBookingLinks(Venue $venue, User $owner): void
    {
        $destination = app(ExternalBookingDestinationManager::class)->save($venue, $owner, [
            'provider_name' => 'Existing Booking Platform · Demo',
            'destination_url' => (string) config('demo-video.external_booking_url'),
            'is_active' => true,
        ]);
        $manager = app(VisibilityLinkManager::class);
        $definitions = [
            [AcquisitionSource::Facebook, 'Facebook bookings', 42],
            [AcquisitionSource::GoogleMaps, 'Google profile bookings', 34],
            [AcquisitionSource::Instagram, 'Instagram bio bookings', 27],
            [AcquisitionSource::QrCode, 'Front desk QR', 19],
            [AcquisitionSource::SharedLink, 'Player shared link', 13],
        ];
        $now = CarbonImmutable::now($venue->organization->timezone);
        $monthKey = $now->format('Ym');

        foreach ($definitions as $definitionIndex => [$source, $label, $clicks]) {
            $link = $manager->createExternal($venue, $destination, $owner, $source, $label);

            foreach (range(1, $clicks) as $index) {
                $occurredAt = $this->currentMonthMoment($now, $index)->subMinutes($index % 50);
                $visitorHash = hash('sha256', "demo-video-external|{$monthKey}|{$source->value}|".($index % max(4, intdiv($clicks, 2))));
                $this->event(
                    $venue,
                    null,
                    null,
                    AnalyticsEventType::ExternalBookingLinkClick,
                    $source,
                    $visitorHash,
                    $occurredAt,
                    "external|{$monthKey}|{$source->value}|{$index}",
                    $link,
                );
            }

            $link->forceFill([
                'is_active' => true,
                'visits_count' => $clicks,
                'last_visited_at' => $now->subMinutes($definitionIndex + 1)->utc(),
            ])->save();
        }
    }
}
