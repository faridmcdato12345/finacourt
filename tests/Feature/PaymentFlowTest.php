<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PlayerPaymentOption;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\OwnerBookingConfirmedNotification;
use App\Payments\Contracts\WebhookPaymentProvider;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\HostedCheckout;
use App\Payments\PaymentProviderRegistry;
use App\Payments\VerifiedPaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_page_shows_clear_online_and_pay_at_venue_choices(): void
    {
        $this->enablePayMongo();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => now('Asia/Manila')->addDays(7)->toDateString(),
            'start' => '09:00',
            'duration' => 60,
        ]))
            ->assertOk()
            ->assertSee('How would you like to pay?')
            ->assertSee('Pay online')
            ->assertSee('Pay at venue')
            ->assertSee('Card')
            ->assertSee('GCash')
            ->assertSee('QR Ph')
            ->assertSee('name="payment_option"', false)
            ->assertDontSee('Not available right now.');
    }

    public function test_player_can_choose_online_payment_while_manual_remains_the_default(): void
    {
        $this->enablePayMongo();
        config()->set('payments.default', 'manual');
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $booking = $this->createHold($player, $venue, $resource, [
            'payment_option' => PlayerPaymentOption::Online->value,
        ]);

        $this->assertSame(PaymentMode::HostedCheckout, $booking->payment_mode);
        $this->assertSame('paymongo', $booking->payment->provider);
        $this->assertSame(PaymentMode::HostedCheckout, $booking->payment->mode);
    }

    public function test_player_can_choose_pay_at_venue_even_when_paymongo_is_the_default(): void
    {
        $this->enablePayMongo();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $booking = $this->createHold($player, $venue, $resource, [
            'payment_option' => PlayerPaymentOption::PayAtVenue->value,
            'payment_provider' => 'paymongo',
            'payment_mode' => PaymentMode::HostedCheckout->value,
        ]);

        $this->assertSame(PaymentMode::PayAtVenue, $booking->payment_mode);
        $this->assertSame('manual', $booking->payment->provider);
        $this->assertSame(PaymentMode::PayAtVenue, $booking->payment->mode);
    }

    public function test_online_choice_is_rejected_when_secure_checkout_is_not_ready(): void
    {
        $this->enablePayMongo();
        config()->set('payments.default', 'manual');
        config()->set('payments.providers.paymongo.webhook_secret', '');
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'payment_option' => PlayerPaymentOption::Online->value,
            'customer_name' => 'Pat Player',
            'terms' => '1',
        ])->assertSessionHasErrors('payment_option');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unknown_player_payment_choice_is_rejected(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'payment_option' => 'free_payment',
            'customer_name' => 'Pat Player',
            'terms' => '1',
        ])->assertSessionHasErrors('payment_option');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_attempt_uses_booking_snapshot_and_ignores_browser_amount(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource, [
            'amount' => '0.01',
            'currency' => 'USD',
            'payment_status' => 'paid',
        ]);
        $payment = $booking->payment()->firstOrFail();

        $this->assertSame('650.00', $payment->amount);
        $this->assertSame('PHP', $payment->currency);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertStringStartsWith('PAY-', $payment->reference);
        $this->assertDatabaseHas('payment_transitions', [
            'payment_id' => $payment->getKey(),
            'from_status' => null,
            'to_status' => PaymentStatus::Pending->value,
            'source' => 'application',
        ]);
    }

    public function test_hosted_checkout_adapter_receives_server_amount_and_return_does_not_mark_paid(): void
    {
        $provider = $this->installWebhookProvider();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);

        $this->actingAs($player)
            ->post(route('player.bookings.checkout', $booking->reference), ['amount' => '1.00'])
            ->assertRedirect($provider->checkoutUrl($booking->payment->reference));

        $payment = $booking->payment->refresh();
        $this->assertSame('650.00', $provider->observedAmount);
        $this->assertSame('PHP', $provider->observedCurrency);
        $this->assertSame($provider->providerReference($payment), $payment->provider_reference);

        $this->actingAs($player)
            ->get(route('player.bookings.payment.return', $booking->reference))
            ->assertRedirect(route('player.bookings.show', $booking->reference));

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Hold, $booking->refresh()->status);
        $this->actingAs($player)
            ->post(route('player.bookings.confirm', $booking->reference))
            ->assertSessionHasErrors('booking');
    }

    public function test_paymongo_checkout_session_uses_server_amount_and_configured_methods(): void
    {
        $this->enablePayMongo();
        Http::fake([
            'https://api.paymongo.test/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_checkout_123',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.test/cs_test_checkout_123',
                    ],
                ],
            ]),
        ]);

        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource, [
            'amount' => '1.00',
            'payment_status' => PaymentStatus::Paid->value,
        ]);
        $payment = $booking->payment->refresh();

        $this->assertSame('paymongo', $payment->provider);
        $this->assertSame(PaymentMode::HostedCheckout, $payment->mode);
        $this->assertSame(PaymentMode::HostedCheckout, $booking->payment_mode);

        $this->actingAs($player)
            ->post(route('player.bookings.checkout', $booking->reference), ['amount' => '1.00'])
            ->assertRedirect('https://checkout.paymongo.test/cs_test_checkout_123');

        $payment->refresh();
        $this->assertSame('650.00', $payment->amount);
        $this->assertSame('cs_test_checkout_123', $payment->provider_reference);

        Http::assertSent(function ($request) use ($payment, $booking): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.paymongo.test/v2/checkout_sessions'
                && $request->hasHeader('Idempotency-Key', $payment->reference)
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_fincourt:'))
                && data_get($body, 'data.attributes.reference_number') === $payment->reference
                && data_get($body, 'data.attributes.line_items.0.amount') === 65000
                && data_get($body, 'data.attributes.line_items.0.currency') === 'PHP'
                && data_get($body, 'data.attributes.payment_method_types') === ['card', 'gcash', 'qrph']
                && data_get($body, 'data.attributes.metadata.payment_reference') === $payment->reference
                && data_get($body, 'data.attributes.metadata.booking_reference') === $booking->reference
                && data_get($body, 'data.attributes.metadata.expected_amount_centavos') === '65000'
                && data_get($body, 'data.attributes.success_url') === route('player.bookings.payment.return', $booking->reference)
                && data_get($body, 'data.attributes.cancel_url') === route('player.bookings.show', $booking->reference);
        });
    }

    public function test_paymongo_signed_checkout_webhook_marks_paid_and_confirms_hold(): void
    {
        $this->enablePayMongo();
        Http::fake([
            'https://api.paymongo.test/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_paid_123',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_test_paid_123'],
                ],
            ]),
        ]);

        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $this->actingAs($player)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();
        $payload = $this->payMongoCheckoutPayload($payment, $booking, 'evt_paymongo_paid', 'cs_test_paid_123');

        $this->postPayMongoWebhook($payload)
            ->assertOk()
            ->assertJsonPath('result', 'processed');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $transition = $payment->transitions()->whereNotNull('external_event_id')->firstOrFail();
        $this->assertSame('paymongo:evt_paymongo_paid', $transition->external_event_id);
        $this->assertSame('gcash', $transition->metadata['paymongo_payment_method']);
    }

    public function test_current_paymongo_v2_webhook_shape_is_verified_and_idempotent(): void
    {
        Notification::fake();
        $this->enablePayMongo();
        Http::fake([
            'https://api.paymongo.test/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_current_v2',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_test_current_v2'],
                ],
            ]),
        ]);

        [, $venue, $resource, $owner] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $this->actingAs($player)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();
        $payload = $this->payMongoV2CheckoutPayload($payment, $booking, 'cs_test_current_v2');

        $this->postPayMongoWebhook($payload)->assertOk()->assertJsonPath('result', 'processed');
        $this->postPayMongoWebhook($payload)->assertOk()->assertJsonPath('result', 'duplicate');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertSame('qrph', $payment->transitions()->whereNotNull('external_event_id')->sole()->metadata['paymongo_payment_method']);
        $this->assertNotNull($booking->owner_confirmation_notified_at);
        Notification::assertSentToTimes($owner, OwnerBookingConfirmedNotification::class, 1);
        Notification::assertSentTo($owner, fn (OwnerBookingConfirmedNotification $notification): bool => $notification->paymentLabel === 'Paid online'
            && $notification->bookingReference === $booking->reference);
    }

    public function test_paymongo_configuration_rejects_a_secret_key_from_the_wrong_mode(): void
    {
        $this->enablePayMongo();
        config()->set('payments.providers.paymongo.secret_key', 'sk_live_wrong_environment');

        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'customer_name' => 'Pat Player',
            'terms' => '1',
        ])->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paymongo_webhook_rejects_invalid_signature(): void
    {
        $this->enablePayMongo();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $payment = $booking->payment;
        $payload = $this->payMongoCheckoutPayload($payment, $booking, 'evt_invalid_signature', 'cs_test_invalid');

        $this->withHeader('Paymongo-Signature', 'bad-signature')
            ->postJson(route('webhooks.payments', 'paymongo'), $payload)
            ->assertUnauthorized();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Hold, $booking->refresh()->status);
    }

    public function test_paymongo_requires_webhook_secret_before_creating_online_hold(): void
    {
        $this->enablePayMongo();
        config()->set('payments.providers.paymongo.webhook_secret', '');

        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'customer_name' => 'Pat Player',
            'terms' => '1',
        ])->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paymongo_wrong_amount_requires_review_without_confirming_booking(): void
    {
        $this->enablePayMongo();
        Http::fake([
            'https://api.paymongo.test/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_wrong_amount',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_test_wrong_amount'],
                ],
            ]),
        ]);

        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $this->actingAs($player)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();
        $payload = $this->payMongoCheckoutPayload($payment, $booking, 'evt_wrong_amount', 'cs_test_wrong_amount', 100);

        $this->postPayMongoWebhook($payload)
            ->assertOk()
            ->assertJsonPath('result', 'review');

        $this->assertTrue($payment->refresh()->requires_review);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(BookingStatus::Hold, $booking->refresh()->status);
    }

    public function test_valid_verified_webhook_marks_paid_and_confirms_active_hold(): void
    {
        $provider = $this->installWebhookProvider();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $this->actingAs($player)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();
        $event = $this->eventData($payment, PaymentStatus::Paid, 'evt-paid-1');

        $this->postWebhook($provider, $event)
            ->assertOk()
            ->assertJsonPath('result', 'processed');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertNull($booking->expires_at);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $provider = $this->installWebhookProvider();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $payment = $booking->payment;

        $this->withHeader('X-Payment-Signature', 'invalid')
            ->postJson(route('webhooks.payments', $provider->key()), $this->eventData($payment, PaymentStatus::Paid))
            ->assertUnauthorized();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Hold, $booking->refresh()->status);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $provider = $this->installWebhookProvider();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $this->actingAs($player)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();
        $event = $this->eventData($payment, PaymentStatus::Paid, 'evt-duplicate');

        $this->postWebhook($provider, $event)->assertJsonPath('result', 'processed');
        $this->postWebhook($provider, $event)->assertJsonPath('result', 'duplicate');

        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(2, $payment->transitions()->count());
    }

    public function test_incorrect_webhook_amount_or_reference_is_flagged_without_confirmation(): void
    {
        $provider = $this->installWebhookProvider();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $this->actingAs($player)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();

        $wrongAmount = $this->eventData($payment, PaymentStatus::Paid, 'evt-wrong-amount');
        $wrongAmount['amount'] = '1.00';
        $this->postWebhook($provider, $wrongAmount)->assertJsonPath('result', 'review');

        $this->assertTrue($payment->refresh()->requires_review);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(BookingStatus::Hold, $booking->refresh()->status);

        $payment->update(['requires_review' => false, 'review_reason' => null]);
        $malformedAmount = $this->eventData($payment, PaymentStatus::Paid, 'evt-malformed-amount');
        $malformedAmount['amount'] = '650.009';
        $this->postWebhook($provider, $malformedAmount)->assertJsonPath('result', 'review');
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        $payment->update(['requires_review' => false, 'review_reason' => null]);
        $wrongReference = $this->eventData($payment, PaymentStatus::Paid, 'evt-wrong-reference');
        $wrongReference['provider_reference'] = 'different-provider-reference';
        $this->postWebhook($provider, $wrongReference)->assertJsonPath('result', 'review');
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_failed_provider_payment_cancels_active_hold_and_releases_slot(): void
    {
        $provider = $this->installWebhookProvider();
        [, $venue, $resource] = $this->setupInventory();
        $playerA = User::factory()->create();
        $booking = $this->createHold($playerA, $venue, $resource);
        $this->actingAs($playerA)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();

        $this->postWebhook($provider, $this->eventData($payment, PaymentStatus::Failed, 'evt-failed'))
            ->assertJsonPath('result', 'processed');
        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);

        config()->set('payments.default', 'manual');
        $playerB = User::factory()->create();
        $this->createHold($playerB, $venue, $resource);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_paid_event_after_hold_expiry_requires_review_and_does_not_confirm(): void
    {
        $provider = $this->installWebhookProvider();
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $this->actingAs($player)->post(route('player.bookings.checkout', $booking->reference));
        $payment = $booking->payment->refresh();
        $booking->update(['expires_at' => now()->subSecond()]);

        $this->postWebhook($provider, $this->eventData($payment, PaymentStatus::Paid, 'evt-late-paid'))
            ->assertJsonPath('result', 'review');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertTrue($payment->requires_review);
        $this->assertSame(BookingStatus::Expired, $booking->refresh()->effectiveStatus());
    }

    public function test_owner_can_idempotently_verify_manual_payment_and_record_refund(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $payment = $booking->payment;

        $payload = ['status' => PaymentStatus::Paid->value, 'amount' => '0.01'];
        $this->actingAs($owner)->patch(route('owner.bookings.payment.update', $booking), $payload)
            ->assertRedirect();
        $this->actingAs($owner)->patch(route('owner.bookings.payment.update', $booking), $payload)
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame('650.00', $payment->amount);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertSame(2, $payment->transitions()->count());

        $this->actingAs($owner)->patch(route('owner.bookings.payment.update', $booking), [
            'status' => PaymentStatus::Refunded->value,
            'note' => 'Cash returned at front desk.',
        ])->assertRedirect();

        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertSame('650.00', $payment->refunded_amount);
        $this->assertSame($organization->getKey(), $payment->organization_id);
    }

    public function test_other_tenant_and_unprivileged_staff_cannot_update_payment(): void
    {
        [, $venue, $resource, $owner] = $this->setupInventory();
        $player = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        [, , , $otherOwner] = $this->setupInventory(['slug' => 'other-venue']);
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($booking->organization)->create();

        $this->actingAs($otherOwner)
            ->patch(route('owner.bookings.payment.update', $booking), ['status' => 'paid'])
            ->assertNotFound();
        $this->actingAs($staff)
            ->patch(route('owner.bookings.payment.update', $booking), ['status' => 'paid'])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $booking->payment->refresh()->status);
        $this->assertSame(BookingStatus::Hold, $booking->refresh()->status);
        $this->assertNotNull($owner);
    }

    public function test_payment_status_is_visible_only_in_its_player_and_tenant_contexts(): void
    {
        [, $venue, $resource, $owner] = $this->setupInventory();
        $player = User::factory()->create();
        $otherPlayer = User::factory()->create();
        $booking = $this->createHold($player, $venue, $resource);
        $payment = $booking->payment;
        [, , , $otherOwner] = $this->setupInventory(['slug' => 'visibility-other-venue']);
        $date = $booking->start_at->setTimezone($booking->timezone)->toDateString();

        $this->actingAs($player)
            ->get(route('player.bookings.show', $booking->reference))
            ->assertOk()
            ->assertSee($payment->reference);
        $this->actingAs($otherPlayer)
            ->get(route('player.bookings.show', $booking->reference))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('owner.bookings.index', ['date' => $date]))
            ->assertOk()
            ->assertSee($payment->reference);
        $this->actingAs($otherOwner)
            ->get(route('owner.bookings.index', ['date' => $date]))
            ->assertOk()
            ->assertDontSee($payment->reference);
    }

    public function test_manual_provider_has_no_webhook_surface(): void
    {
        $this->postJson(route('webhooks.payments', 'manual'), [])->assertNotFound();
    }

    /** @param array<string, mixed> $venueAttributes
     * @return array{Organization, Venue, CourtResource, User}
     */
    private function setupInventory(array $venueAttributes = []): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => 'Payment Test Courts',
            'slug' => 'payment-test-courts',
            'city' => 'Makati',
            'city_slug' => 'makati',
            'province' => 'Metro Manila',
            'province_slug' => 'metro-manila',
            ...$venueAttributes,
        ]);
        $sport = Sport::query()->firstOrCreate(
            ['slug' => 'badminton'],
            ['name' => 'Badminton', 'is_active' => true],
        );
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        return [$organization, $venue, $resource, $owner];
    }

    /** @param array<string, mixed> $extra */
    private function createHold(User $player, Venue $venue, CourtResource $resource, array $extra = []): Booking
    {
        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'customer_name' => 'Pat Player',
            'terms' => '1',
            ...$extra,
        ])->assertRedirect();

        return Booking::query()->where('player_user_id', $player->getKey())->latest('id')->firstOrFail()->load('payment');
    }

    private function installWebhookProvider(): FakeWebhookPaymentProvider
    {
        $provider = new FakeWebhookPaymentProvider('test-webhook-secret');
        app(PaymentProviderRegistry::class)->register($provider);
        config()->set('payments.default', $provider->key());

        return $provider;
    }

    private function enablePayMongo(): void
    {
        config()->set('payments.default', 'paymongo');
        config()->set('payments.online_provider', 'paymongo');
        config()->set('payments.providers.paymongo.enabled', true);
        config()->set('payments.providers.paymongo.mode', 'test');
        config()->set('payments.providers.paymongo.api_base_url', 'https://api.paymongo.test');
        config()->set('payments.providers.paymongo.secret_key', 'sk_test_fincourt');
        config()->set('payments.providers.paymongo.webhook_secret', 'whsk_test_fincourt');
        config()->set('payments.providers.paymongo.payment_method_types', ['card', 'gcash', 'qrph']);
        config()->set('payments.providers.paymongo.send_email_receipt', true);
        config()->set('payments.providers.paymongo.pass_on_fees', false);
        config()->set('payments.providers.paymongo.signature_tolerance_seconds', 300);
    }

    private function payMongoCheckoutPayload(
        Payment $payment,
        Booking $booking,
        string $eventId,
        string $checkoutSessionId,
        int $amountCentavos = 65000,
    ): array {
        return [
            'data' => [
                'id' => $eventId,
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'created_at' => now('UTC')->timestamp,
                    'data' => [
                        'id' => $checkoutSessionId,
                        'type' => 'checkout_session',
                        'attributes' => [
                            'reference_number' => $payment->reference,
                            'metadata' => [
                                'payment_reference' => $payment->reference,
                                'booking_reference' => $booking->reference,
                                'expected_amount_centavos' => '65000',
                            ],
                            'line_items' => [[
                                'name' => $booking->venue->name.' · '.$booking->resource->name,
                                'amount' => $amountCentavos,
                                'currency' => 'PHP',
                                'quantity' => 1,
                            ]],
                            'payments' => [[
                                'id' => 'pay_'.$eventId,
                                'type' => 'payment',
                                'attributes' => [
                                    'amount' => $amountCentavos,
                                    'fee' => 0,
                                    'net_amount' => $amountCentavos,
                                    'currency' => 'PHP',
                                    'status' => 'paid',
                                    'source' => ['type' => 'gcash'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function payMongoV2CheckoutPayload(
        Payment $payment,
        Booking $booking,
        string $checkoutSessionId,
    ): array {
        return [
            'event_type' => 'send.webhook',
            'data' => [
                'type' => 'checkout_session.payment.paid',
                'resource' => 'checkout_session',
                'livemode' => false,
                'organization_id' => 'org_test_fincourt',
                'created_at' => now('UTC')->toIso8601String(),
                'updated_at' => now('UTC')->toIso8601String(),
                'data' => [
                    'id' => $checkoutSessionId,
                    'type' => 'checkout_session',
                    'attributes' => [
                        'reference_number' => $payment->reference,
                        'metadata' => [
                            'payment_reference' => $payment->reference,
                            'booking_reference' => $booking->reference,
                            'expected_amount_centavos' => '65000',
                        ],
                        'line_items' => [[
                            'name' => $booking->venue->name.' · '.$booking->resource->name,
                            'amount' => 65000,
                            'currency' => 'PHP',
                            'quantity' => 1,
                        ]],
                        'payment_intent' => ['id' => 'pi_test_current_v2'],
                        'payments' => [[
                            'id' => 'pay_test_current_v2',
                            'attributes' => [
                                'amount' => 65000,
                                'fee' => 1300,
                                'net_amount' => 63700,
                                'currency' => 'PHP',
                                'status' => 'paid',
                                'source' => ['type' => 'qrph'],
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }

    private function postPayMongoWebhook(array $payload)
    {
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now('UTC')->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, 'whsk_test_fincourt');

        return $this->call(
            'POST',
            route('webhooks.payments', 'paymongo'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
            ],
            $rawPayload,
        );
    }

    /** @return array<string, string> */
    private function eventData(
        Payment $payment,
        PaymentStatus $status,
        string $eventId = 'evt-default',
    ): array {
        return [
            'event_id' => $eventId,
            'payment_reference' => $payment->reference,
            'provider_reference' => $payment->provider_reference ?? 'provider-'.$payment->reference,
            'status' => $status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ];
    }

    /** @param array<string, string> $event */
    private function postWebhook(FakeWebhookPaymentProvider $provider, array $event)
    {
        return $this->withHeader('X-Payment-Signature', $provider->sign($event))
            ->postJson(route('webhooks.payments', $provider->key()), $event);
    }
}

class FakeWebhookPaymentProvider implements WebhookPaymentProvider
{
    public ?string $observedAmount = null;

    public ?string $observedCurrency = null;

    public function __construct(private readonly string $secret) {}

    public function key(): string
    {
        return 'test-hosted';
    }

    public function mode(): PaymentMode
    {
        return PaymentMode::HostedCheckout;
    }

    public function supportsHostedCheckout(): bool
    {
        return true;
    }

    public function createHostedCheckout(Payment $payment): HostedCheckout
    {
        $this->observedAmount = $payment->amount;
        $this->observedCurrency = $payment->currency;

        return new HostedCheckout(
            $this->checkoutUrl($payment->reference),
            $this->providerReference($payment),
        );
    }

    public function verifyWebhook(Request $request): VerifiedPaymentEvent
    {
        $signature = (string) $request->header('X-Payment-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $this->secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignature;
        }

        $data = $request->json()->all();

        return new VerifiedPaymentEvent(
            eventId: $data['event_id'],
            paymentReference: $data['payment_reference'],
            providerReference: $data['provider_reference'],
            status: PaymentStatus::from($data['status']),
            amount: $data['amount'],
            currency: $data['currency'],
        );
    }

    /** @param array<string, string> $event */
    public function sign(array $event): string
    {
        return hash_hmac('sha256', json_encode($event, JSON_THROW_ON_ERROR), $this->secret);
    }

    public function checkoutUrl(string $paymentReference): string
    {
        return 'https://payments.example.test/checkout/'.$paymentReference;
    }

    public function providerReference(Payment $payment): string
    {
        return 'provider-'.$payment->reference;
    }
}
