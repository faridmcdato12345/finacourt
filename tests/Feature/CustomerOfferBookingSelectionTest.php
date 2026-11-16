<?php

namespace Tests\Feature;

use App\Bookings\CreateBooking;
use App\CustomerOffers\CustomerOfferService;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CustomerOfferDiscountType;
use App\Enums\PaymentMode;
use App\Enums\PromotionDiscountType;
use App\Models\CourtResource;
use App\Models\CustomerOfferCampaign;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\HostedCheckout;
use App\Payments\PaymentProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOfferBookingSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_offer_wins_only_when_it_is_strictly_better_than_the_public_promotion(): void
    {
        [$organization, $venue, $resource, $player] = $this->inventory();
        $promotion = $this->publicPromotion($venue, $resource, '900.00');
        $offer = app(CustomerOfferService::class)->issue(
            $this->offerCampaign($venue, CustomerOfferDiscountType::Fixed, '200.00'),
            $player,
        );

        $booking = $this->createBooking($organization, $resource, $player, $promotion->campaign_token, 3);

        $this->assertNull($booking->promotion_id);
        $this->assertSame($offer->getKey(), $booking->customer_offer_id);
        $this->assertSame('200.00', $booking->customer_offer_discount_amount);
        $this->assertSame('800.00', $booking->total_amount);
        $this->assertSame('800.00', $booking->payment->amount);
    }

    public function test_equal_private_and_public_discounts_keep_the_public_promotion_and_do_not_reserve_the_offer(): void
    {
        [$organization, $venue, $resource, $player] = $this->inventory();
        $promotion = $this->publicPromotion($venue, $resource, '900.00');
        $offer = app(CustomerOfferService::class)->issue(
            $this->offerCampaign($venue, CustomerOfferDiscountType::Fixed, '100.00'),
            $player,
        );

        $booking = $this->createBooking($organization, $resource, $player, $promotion->campaign_token, 4);

        $this->assertSame($promotion->getKey(), $booking->promotion_id);
        $this->assertNull($booking->customer_offer_id);
        $this->assertSame('100.00', $booking->discount_amount);
        $this->assertSame('900.00', $booking->total_amount);
        $this->assertSame('available', $offer->refresh()->status->value);
    }

    /** @return array{Organization, Venue, CourtResource, User} */
    private function inventory(): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $venue = Venue::factory()->published()->for($organization)->create();
        $sport = Sport::factory()->create(['is_active' => true]);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => '1000.00',
            'booking_increment_minutes' => 60,
            'is_active' => true,
        ]);
        foreach (range(0, 6) as $weekday) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $weekday,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
                'is_closed' => false,
            ]);
        }
        app(PaymentProviderRegistry::class)->register(new CustomerOfferTestPaymentProvider);

        return [$organization, $venue, $resource, User::factory()->create()];
    }

    private function publicPromotion(Venue $venue, CourtResource $resource, string $hourlyRate): Promotion
    {
        return Promotion::factory()->for($venue)->create([
            'organization_id' => $venue->organization_id,
            'resource_id' => $resource->getKey(),
            'discount_type' => PromotionDiscountType::FixedHourlyRate,
            'discount_value' => $hourlyRate,
            'starts_on' => now('Asia/Manila')->subDay()->toDateString(),
            'ends_on' => now('Asia/Manila')->addMonth()->toDateString(),
        ]);
    }

    private function offerCampaign(Venue $venue, CustomerOfferDiscountType $type, string $value): CustomerOfferCampaign
    {
        return CustomerOfferCampaign::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'name' => 'Private comeback offer',
            'discount_type' => $type,
            'discount_value' => $value,
            'valid_days' => 14,
            'is_active' => true,
        ]);
    }

    private function createBooking(Organization $organization, CourtResource $resource, User $player, string $campaign, int $daysAhead)
    {
        return app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addDays($daysAhead)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'customer_email' => $player->email,
            'create_payment' => true,
            'payment_provider' => 'customer-offer-test',
            'campaign' => $campaign,
        ], $player)->load('payment');
    }
}

class CustomerOfferTestPaymentProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'customer-offer-test';
    }

    public function mode(): PaymentMode
    {
        return PaymentMode::HostedCheckout;
    }

    public function supportsHostedCheckout(): bool
    {
        return true;
    }

    public function createHostedCheckout(\App\Models\Payment $payment): HostedCheckout
    {
        return new HostedCheckout('https://example.test/checkout/'.$payment->reference, 'checkout-'.$payment->reference);
    }
}
