<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\OwnerSettlementEntryType;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RefundNotification;
use App\Settlements\OwnerSettlementLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AutomaticRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->enablePayMongo();
    }

    public function test_player_can_idempotently_request_a_full_online_refund_for_venue_review(): void
    {
        [$organization, $owner, $player, $booking, $payment] = $this->setupOnlinePayment();

        $this->actingAs($player)
            ->post(route('player.bookings.refunds.store', $booking->reference), [
                'reason' => 'The team can no longer attend the booking.',
            ])
            ->assertRedirect(route('player.bookings.show', $booking->reference));

        $this->assertDatabaseHas('refund_requests', [
            'organization_id' => $organization->getKey(),
            'booking_id' => $booking->getKey(),
            'payment_id' => $payment->getKey(),
            'status' => RefundRequestStatus::Requested->value,
            'amount' => '675.00',
            'currency' => 'PHP',
        ]);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $refundRequest = RefundRequest::query()->sole();

        $this->actingAs($player)->post(route('player.bookings.refunds.store', $booking->reference), [
            'reason' => 'A duplicate click must not create another refund.',
        ]);

        $this->assertDatabaseCount('refund_requests', 1);
        Notification::assertSentTo($owner, RefundNotification::class, fn (RefundNotification $notification): bool => $notification->kind === 'refund_requested'
            && str_ends_with($notification->url, '#refund-request-'.$refundRequest->getKey()));
        Notification::assertSentTo($player, RefundNotification::class, fn (RefundNotification $notification): bool => $notification->kind === 'refund_requested');
    }

    public function test_player_cannot_request_a_refund_inside_the_24_hour_cutoff(): void
    {
        [, , $player, $booking, $payment] = $this->setupOnlinePayment();
        $booking->update([
            'start_at' => now('UTC')->addHours(23),
            'end_at' => now('UTC')->addHours(24),
        ]);
        $payment->update(['paid_at' => now('UTC')->subMinutes(16)]);

        $response = $this->actingAs($player)
            ->post(route('player.bookings.refunds.store', $booking->reference), [
                'reason' => 'The team can no longer attend the booking.',
            ])
            ->assertSessionHasErrors('refund');

        $this->assertStringContainsString(
            'Player-requested refunds must normally be submitted at least 24 hours before the booking begins.',
            $response->getSession()->get('errors')->first('refund'),
        );

        $this->assertDatabaseCount('refund_requests', 0);
        Notification::assertNothingSent();

        $this->get(route('player.bookings.show', $booking->reference))
            ->assertOk()
            ->assertSee('The player refund window has closed')
            ->assertSee('This booking included a 15-minute refund grace period after payment.')
            ->assertDontSee('Request full refund');
    }

    public function test_new_short_notice_booking_receives_a_15_minute_refund_grace_period(): void
    {
        [, , $player, $booking] = $this->setupOnlinePayment();
        $booking->update([
            'start_at' => now('UTC')->addHours(3),
            'end_at' => now('UTC')->addHours(4),
        ]);

        $this->actingAs($player)
            ->get(route('player.bookings.show', $booking->reference))
            ->assertOk()
            ->assertSee('This short-notice booking has a 15-minute refund grace period after payment.')
            ->assertSee('Request full refund');

        $this->post(route('player.bookings.refunds.store', $booking->reference), [
            'reason' => 'We noticed immediately that the selected time was wrong.',
        ])->assertRedirect(route('player.bookings.show', $booking->reference));

        $this->assertDatabaseHas('refund_requests', [
            'booking_id' => $booking->getKey(),
            'status' => RefundRequestStatus::Requested->value,
        ]);
    }

    public function test_player_can_request_a_refund_at_the_exact_configured_deadline(): void
    {
        $this->travelTo('2026-09-19 10:00:00');
        config()->set('refunds.player_request_cutoff_hours', 48);
        [, , $player, $booking, $payment] = $this->setupOnlinePayment();
        $booking->update([
            'start_at' => now('UTC')->addHours(48),
            'end_at' => now('UTC')->addHours(49),
        ]);
        $payment->update(['paid_at' => now('UTC')->subMinutes(16)]);

        $this->actingAs($player)
            ->post(route('player.bookings.refunds.store', $booking->reference), [
                'reason' => 'The team can no longer attend the booking.',
            ])
            ->assertRedirect(route('player.bookings.show', $booking->reference));

        $this->assertDatabaseHas('refund_requests', [
            'booking_id' => $booking->getKey(),
            'status' => RefundRequestStatus::Requested->value,
        ]);
    }

    public function test_owner_can_decline_request_without_changing_booking_or_payment(): void
    {
        [$organization, $owner, $player, $booking, $payment] = $this->setupOnlinePayment();
        $refundRequest = $this->requestRefund($player, $booking);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.refunds.reject', $refundRequest), [
                'note' => 'The booking is outside the agreed cancellation window.',
            ])
            ->assertRedirect();

        $this->assertSame(RefundRequestStatus::Rejected, $refundRequest->refresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        Notification::assertSentTo($player, RefundNotification::class, fn (RefundNotification $notification): bool => $notification->kind === 'refund_rejected');
    }

    public function test_owner_refund_queue_is_visible_independently_of_selected_schedule_date(): void
    {
        [$organization, $owner, $player, $booking] = $this->setupOnlinePayment();
        $refundRequest = $this->requestRefund($player, $booking);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.bookings.index', ['date' => now('Asia/Manila')->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Bookings/Index')
                ->has('bookings', 0)
                ->where('refundBookings.0.refund_request.id', $refundRequest->getKey())
                ->where('refundBookings.0.reference', $booking->reference));
    }

    public function test_owner_approval_submits_full_refund_and_reverses_court_earnings(): void
    {
        [$organization, $owner, $player, $booking, $payment] = $this->setupOnlinePayment();
        app(OwnerSettlementLedger::class)->recordPaidPayment($payment);
        $refundRequest = $this->requestRefund($player, $booking);

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => Http::response([
                'data' => [
                    'id' => 'ref_test_succeeded',
                    'type' => 'refund',
                    'attributes' => [
                        'amount' => 67500,
                        'currency' => 'PHP',
                        'payment_id' => $payment->provider_payment_reference,
                        'status' => 'succeeded',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.refunds.approve', $refundRequest), ['note' => 'Approved in full.'])
            ->assertRedirect();

        $this->assertSame(RefundRequestStatus::Refunded, $refundRequest->refresh()->status);
        $this->assertSame('ref_test_succeeded', $refundRequest->provider_refund_reference);
        $this->assertSame(1, $refundRequest->attempts);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertSame('675.00', $payment->refunded_amount);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertDatabaseHas('owner_settlement_entries', [
            'payment_id' => $payment->getKey(),
            'type' => OwnerSettlementEntryType::RefundAdjustment->value,
            'amount' => '-650.00',
        ]);

        Http::assertSent(function ($request) use ($payment, $refundRequest): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.paymongo.test/v1/refunds'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_fincourt:'))
                && $request->hasHeader('Idempotency-Key', $refundRequest->reference.'-1')
                && data_get($request->data(), 'data.attributes.amount') === 67500
                && data_get($request->data(), 'data.attributes.payment_id') === $payment->provider_payment_reference
                && data_get($request->data(), 'data.attributes.metadata.refund_request_id') === (string) $refundRequest->getKey();
        });
        Notification::assertSentTo($player, RefundNotification::class, fn (RefundNotification $notification): bool => $notification->kind === 'refund_refunded');
    }

    public function test_processing_refund_is_finalized_by_signed_idempotent_webhook(): void
    {
        [$organization, $owner, $player, $booking, $payment] = $this->setupOnlinePayment();
        $refundRequest = $this->requestRefund($player, $booking);

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => Http::response([
                'data' => [
                    'id' => 'ref_test_processing',
                    'type' => 'refund',
                    'attributes' => [
                        'amount' => 67500,
                        'currency' => 'PHP',
                        'payment_id' => $payment->provider_payment_reference,
                        'status' => 'processing',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.refunds.approve', $refundRequest));

        $this->assertSame(RefundRequestStatus::Processing, $refundRequest->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);

        $payload = $this->payMongoRefundPayload(
            eventId: 'evt_refund_succeeded',
            providerRefundReference: 'ref_test_processing',
            providerPaymentReference: $payment->provider_payment_reference,
            amountCentavos: 67500,
            refundRequestId: $refundRequest->getKey(),
        );

        $this->postPayMongoWebhook($payload)->assertOk()->assertJsonPath('result', 'processed');
        $this->postPayMongoWebhook($payload)->assertOk()->assertJsonPath('result', 'duplicate');

        $this->assertSame(RefundRequestStatus::Refunded, $refundRequest->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertSame(1, $payment->transitions()
            ->where('external_event_id', 'paymongo:evt_refund_succeeded')
            ->count());

        $delayedPayload = $this->payMongoRefundPayload(
            eventId: 'evt_refund_delayed_processing',
            providerRefundReference: 'ref_test_processing',
            providerPaymentReference: $payment->provider_payment_reference,
            amountCentavos: 67500,
            refundRequestId: $refundRequest->getKey(),
            providerStatus: 'processing',
        );
        $this->postPayMongoWebhook($delayedPayload)->assertOk()->assertJsonPath('result', 'processed');
        $this->assertSame(RefundRequestStatus::Refunded, $refundRequest->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
    }

    public function test_provider_rejection_is_retryable_and_does_not_release_slot_until_accepted(): void
    {
        [$organization, $owner, $player, $booking, $payment] = $this->setupOnlinePayment();
        $refundRequest = $this->requestRefund($player, $booking);

        Http::fakeSequence('https://api.paymongo.test/v1/refunds')
            ->push([
                'errors' => [[
                    'code' => 'temporary_refund_error',
                    'detail' => 'Refund service is temporarily unavailable.',
                ]],
            ], 422)
            ->push([
                'data' => [
                    'id' => 'ref_test_retry',
                    'attributes' => [
                        'amount' => 67500,
                        'currency' => 'PHP',
                        'payment_id' => $payment->provider_payment_reference,
                        'status' => 'succeeded',
                    ],
                ],
            ]);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.refunds.approve', $refundRequest));

        $this->assertSame(RefundRequestStatus::Failed, $refundRequest->refresh()->status);
        $this->assertFalse($refundRequest->requires_review);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.refunds.retry', $refundRequest));

        $this->assertSame(RefundRequestStatus::Refunded, $refundRequest->refresh()->status);
        $this->assertSame(2, $refundRequest->attempts);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
    }

    public function test_scheduler_reconciles_processing_refund_when_final_webhook_was_missed(): void
    {
        [$organization, $owner, $player, $booking, $payment] = $this->setupOnlinePayment();
        $refundRequest = $this->requestRefund($player, $booking);

        Http::fake(function ($request) use ($payment) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'data' => [
                        'id' => 'ref_test_reconcile',
                        'attributes' => [
                            'amount' => 67500,
                            'currency' => 'PHP',
                            'payment_id' => $payment->provider_payment_reference,
                            'status' => 'processing',
                        ],
                    ],
                ]);
            }

            return Http::response([
                'data' => [
                    'id' => 'ref_test_reconcile',
                    'attributes' => [
                        'amount' => 67500,
                        'currency' => 'PHP',
                        'payment_id' => $payment->provider_payment_reference,
                        'status' => 'succeeded',
                        'updated_at' => now()->timestamp,
                    ],
                ],
            ]);
        });

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.refunds.approve', $refundRequest));
        $refundRequest->refresh()->update(['submitted_at' => now()->subMinutes(3)]);

        $this->artisan('refunds:reconcile-processing')
            ->expectsOutput('Checked 1 processing refund(s); finalized 1.')
            ->assertSuccessful();

        $this->assertSame(RefundRequestStatus::Refunded, $refundRequest->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.paymongo.test/v1/refunds/ref_test_reconcile');
    }

    public function test_external_partial_refund_is_flagged_until_platform_reconciles_a_full_refund(): void
    {
        [, , $player, $booking, $payment] = $this->setupOnlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $payload = $this->payMongoRefundPayload(
            eventId: 'evt_partial_refund',
            providerRefundReference: 'ref_test_partial',
            providerPaymentReference: $payment->provider_payment_reference,
            amountCentavos: 10000,
        );

        $this->postPayMongoWebhook($payload)->assertOk()->assertJsonPath('result', 'review');

        $refundRequest = RefundRequest::query()->sole();
        $this->assertSame(RefundRequestStatus::Failed, $refundRequest->status);
        $this->assertTrue($refundRequest->requires_review);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertTrue($payment->requires_review);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);

        $this->actingAs($admin)->post(route('platform.payments.refunds.store', $payment), [
            'external_reference' => 'ref_test_partial',
            'note' => 'Verified that PayMongo completed the full refund.',
        ])->assertRedirect();

        $this->assertSame(RefundRequestStatus::Refunded, $refundRequest->refresh()->status);
        $this->assertFalse($refundRequest->requires_review);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertFalse($payment->requires_review);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        Notification::assertSentTo($player, RefundNotification::class, fn (RefundNotification $notification): bool => $notification->kind === 'refund_refunded');
    }

    /** @return array{Organization, User, User, Booking, Payment} */
    private function setupOnlinePayment(): array
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $player = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create();
        $resource = CourtResource::factory()->for($venue)->create();
        $booking = Booking::factory()
            ->for($organization)
            ->for($venue)
            ->for($resource, 'resource')
            ->for($player, 'player')
            ->create([
                'source' => BookingSource::Marketplace,
                'status' => BookingStatus::Confirmed,
                'payment_mode' => PaymentMode::HostedCheckout,
                'payment_status' => PaymentStatus::Paid,
                'total_amount' => '650.00',
                'platform_service_fee_amount' => '25.00',
                'player_total_amount' => '675.00',
                'start_at' => now()->addDays(7),
                'end_at' => now()->addDays(7)->addHour(),
            ]);
        $payment = Payment::factory()->for($booking)->create([
            'organization_id' => $organization->getKey(),
            'created_by_user_id' => $player->getKey(),
            'provider' => 'paymongo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Paid,
            'amount' => '675.00',
            'venue_amount' => '650.00',
            'platform_service_fee_amount' => '25.00',
            'provider_reference' => 'cs_test_original',
            'provider_payment_reference' => 'pay_test_original_'.uniqid(),
            'paid_at' => now(),
        ]);

        return [$organization, $owner, $player, $booking, $payment];
    }

    private function requestRefund(User $player, Booking $booking): RefundRequest
    {
        $this->actingAs($player)->post(route('player.bookings.refunds.store', $booking->reference), [
            'reason' => 'Our plans changed and we cannot attend.',
        ]);

        return RefundRequest::query()->where('booking_id', $booking->getKey())->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payMongoRefundPayload(
        string $eventId,
        string $providerRefundReference,
        string $providerPaymentReference,
        int $amountCentavos,
        ?int $refundRequestId = null,
        string $providerStatus = 'succeeded',
    ): array {
        return [
            'data' => [
                'id' => $eventId,
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.refund.updated',
                    'livemode' => false,
                    'data' => [
                        'id' => $providerRefundReference,
                        'type' => 'refund',
                        'attributes' => [
                            'amount' => $amountCentavos,
                            'currency' => 'PHP',
                            'payment_id' => $providerPaymentReference,
                            'status' => $providerStatus,
                            'metadata' => array_filter([
                                'refund_request_id' => $refundRequestId ? (string) $refundRequestId : null,
                            ]),
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
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
        config()->set('payments.providers.paymongo.signature_tolerance_seconds', 300);
    }
}
