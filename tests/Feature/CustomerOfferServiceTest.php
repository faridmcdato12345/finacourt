<?php

namespace Tests\Feature;

use App\CustomerOffers\CustomerOfferService;
use App\Enums\CustomerOfferDiscountType;
use App\Enums\CustomerOfferStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\CustomerOffer;
use App\Models\CustomerOfferCampaign;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Payments\ApplyPaymentTransition;
use App\Payments\ApplyVerifiedPaymentEvent;
use App\Payments\VerifiedPaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_offers_are_account_bound_and_calculated_in_centavos(): void
    {
        [$venue, $player] = $this->venueAndPlayer();
        $otherPlayer = User::factory()->create();
        $campaign = $this->campaign($venue, [
            'discount_type' => CustomerOfferDiscountType::Percentage,
            'discount_value' => '12.50',
            'minimum_spend' => '500.00',
        ]);
        $offer = app(CustomerOfferService::class)->issue($campaign, $player);

        $service = app(CustomerOfferService::class);
        $this->assertNull($service->bestAvailable($venue, $otherPlayer, '1000.00'));
        $this->assertNull($service->quote($offer, '499.99'));
        $this->assertSame([
            'discount_amount' => '125.00',
            'total_amount' => '875.00',
        ], $service->quote($offer, '1000.00'));
    }

    public function test_expired_revoked_and_campaign_capped_offers_are_not_eligible(): void
    {
        [$venue, $player] = $this->venueAndPlayer();
        $campaign = $this->campaign($venue, ['max_redemptions' => 1]);
        $service = app(CustomerOfferService::class);
        $expired = $service->issue($campaign, $player);
        $expired->update(['expires_at' => now('UTC')->subMinute()]);
        $this->assertNull($service->bestAvailable($venue, $player, '500.00'));
        $service->sweepExpiredOffers();
        $this->assertSame(CustomerOfferStatus::Expired, $expired->refresh()->status);

        $revoked = $service->issue($campaign, $player);
        $service->revoke($revoked);
        $this->assertSame(CustomerOfferStatus::Revoked, $revoked->refresh()->status);
        $this->assertNull($service->bestAvailable($venue, $player, '500.00'));

        $redeemed = $service->issue($campaign, $player);
        $redeemed->update(['status' => CustomerOfferStatus::Redeemed, 'redeemed_at' => now('UTC')]);
        $newOffer = $service->issue($campaign, $player);
        $this->assertNull($service->bestAvailable($venue, $player, '500.00'));
        $this->assertSame(CustomerOfferStatus::Available, $newOffer->fresh()->status);
    }

    public function test_a_reserved_offer_is_released_after_an_unsuccessful_checkout_and_redeemed_once_after_payment(): void
    {
        [$venue, $player] = $this->venueAndPlayer();
        $resource = CourtResource::factory()->for($venue)->create();
        $booking = Booking::factory()->hold()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource->getKey(),
            'player_user_id' => $player->getKey(),
            'created_by_user_id' => $player->getKey(),
        ]);
        $service = app(CustomerOfferService::class);
        $offer = $service->issue($this->campaign($venue), $player);
        $booking->update([
            'customer_offer_id' => $offer->getKey(),
            'customer_offer_campaign_id' => $offer->customer_offer_campaign_id,
        ]);

        $service->reserve($offer, $booking, '650.00');
        $this->assertSame(CustomerOfferStatus::Reserved, $offer->refresh()->status);
        $this->assertSame($booking->getKey(), $offer->booking_id);

        $service->releaseForBooking($booking);
        $this->assertSame(CustomerOfferStatus::Available, $offer->refresh()->status);
        $this->assertNull($offer->booking_id);

        $service->reserve($offer, $booking, '650.00');
        $service->redeemForBooking($booking);
        $service->releaseForBooking($booking);

        $this->assertSame(CustomerOfferStatus::Redeemed, $offer->refresh()->status);
        $this->assertNotNull($offer->redeemed_at);
    }

    public function test_failed_payment_releases_a_reserved_offer_and_refunds_do_not_restore_a_redeemed_offer(): void
    {
        [$venue, $player] = $this->venueAndPlayer();
        $resource = CourtResource::factory()->for($venue)->create();
        $service = app(CustomerOfferService::class);

        [$failedBooking, $failedOffer, $failedPayment] = $this->reservedCheckout($venue, $resource, $player, $service);
        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $failedPayment,
            $failedBooking,
            PaymentStatus::Failed,
            'test_payment_failure',
        ));
        $this->assertSame(CustomerOfferStatus::Available, $failedOffer->refresh()->status);

        [$paidBooking, $paidOffer, $paidPayment] = $this->reservedCheckout($venue, $resource, $player, $service);
        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $paidPayment,
            $paidBooking,
            PaymentStatus::Paid,
            'test_payment_paid',
        ));
        $this->assertSame(CustomerOfferStatus::Redeemed, $paidOffer->refresh()->status);

        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $paidPayment->fresh(),
            $paidBooking->fresh(),
            PaymentStatus::Refunded,
            'test_payment_refund',
        ));
        $this->assertSame(CustomerOfferStatus::Redeemed, $paidOffer->refresh()->status);
    }

    public function test_late_paid_event_does_not_consume_an_offer_reserved_by_an_expired_hold(): void
    {
        [$venue, $player] = $this->venueAndPlayer();
        $resource = CourtResource::factory()->for($venue)->create();
        $service = app(CustomerOfferService::class);
        [$booking, $offer, $payment] = $this->reservedCheckout($venue, $resource, $player, $service, expired: true);

        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $payment,
            $booking,
            PaymentStatus::Paid,
            'test_late_payment',
        ));

        $this->assertSame(CustomerOfferStatus::Available, $offer->refresh()->status);
        $this->assertTrue($payment->refresh()->requires_review);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    public function test_the_same_offer_cannot_be_reserved_by_two_checkouts(): void
    {
        [$venue, $player] = $this->venueAndPlayer();
        $resource = CourtResource::factory()->for($venue)->create();
        $service = app(CustomerOfferService::class);
        [$firstBooking, $offer] = $this->reservedCheckout($venue, $resource, $player, $service);
        $secondBooking = Booking::factory()->hold()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource->getKey(),
            'player_user_id' => $player->getKey(),
            'created_by_user_id' => $player->getKey(),
            'customer_offer_id' => $offer->getKey(),
            'customer_offer_campaign_id' => $offer->customer_offer_campaign_id,
        ]);

        try {
            $service->reserve($offer, $secondBooking, '650.00');
            $this->fail('A reserved customer offer must not be available to a second checkout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('offer', $exception->errors());
        }

        $this->assertSame($firstBooking->getKey(), $offer->refresh()->booking_id);
        $this->assertSame(CustomerOfferStatus::Reserved, $offer->status);
    }

    public function test_verified_webhook_is_idempotent_and_redeems_the_offer_once(): void
    {
        [$venue, $player] = $this->venueAndPlayer();
        $resource = CourtResource::factory()->for($venue)->create();
        $service = app(CustomerOfferService::class);
        [$booking, $offer, $payment] = $this->reservedCheckout($venue, $resource, $player, $service);
        $payment->update(['provider' => 'sandbox']);
        $event = new VerifiedPaymentEvent(
            eventId: 'evt-customer-offer-paid',
            paymentReference: $payment->reference,
            providerReference: 'checkout-customer-offer',
            status: PaymentStatus::Paid,
            amount: '650.00',
            currency: 'PHP',
            providerPaymentReference: 'payment-customer-offer',
        );

        $processor = app(ApplyVerifiedPaymentEvent::class);
        $this->assertSame('processed', $processor->handle('sandbox', $event));
        $this->assertSame('duplicate', $processor->handle('sandbox', $event));

        $this->assertSame(CustomerOfferStatus::Redeemed, $offer->refresh()->status);
        $this->assertSame(1, $payment->refresh()->transitions()->count());
        $this->assertDatabaseHas('payment_transitions', [
            'payment_id' => $payment->getKey(),
            'external_event_id' => 'sandbox:evt-customer-offer-paid',
        ]);
        $this->assertSame(Booking::query()->findOrFail($booking->getKey())->status->value, 'confirmed');
    }

    /** @return array{Booking, CustomerOffer, Payment} */
    private function reservedCheckout(
        Venue $venue,
        CourtResource $resource,
        User $player,
        CustomerOfferService $service,
        bool $expired = false,
    ): array {
        $booking = Booking::factory()->hold()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource->getKey(),
            'player_user_id' => $player->getKey(),
            'created_by_user_id' => $player->getKey(),
            'expires_at' => $expired ? now()->subMinute() : now()->addMinutes(15),
        ]);
        $offer = $service->issue($this->campaign($venue), $player);
        $booking->update([
            'customer_offer_id' => $offer->getKey(),
            'customer_offer_campaign_id' => $offer->customer_offer_campaign_id,
        ]);
        $service->reserve($offer, $booking->fresh(), '650.00');
        $payment = Payment::factory()->for($booking)->create([
            'organization_id' => $venue->organization_id,
            'amount' => '650.00',
            'venue_amount' => '650.00',
            'platform_service_fee_amount' => '0.00',
        ]);

        return [$booking->fresh(), $offer, $payment];
    }

    /** @return array{Venue, User} */
    private function venueAndPlayer(): array
    {
        $organization = Organization::factory()->create();

        return [Venue::factory()->for($organization)->create(), User::factory()->create()];
    }

    /** @param array<string, mixed> $attributes */
    private function campaign(Venue $venue, array $attributes = []): CustomerOfferCampaign
    {
        return CustomerOfferCampaign::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'name' => 'Welcome back',
            'discount_type' => CustomerOfferDiscountType::Fixed,
            'discount_value' => '100.00',
            'valid_days' => 7,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
