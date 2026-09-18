<?php

namespace Tests\Feature;

use App\Bookings\AvailabilityService;
use App\CourtClosures\CourtClosureConfirmation;
use App\CourtClosures\CourtClosureImpact;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CourtClosureBookingStatus;
use App\Enums\CourtClosureRefundStatus;
use App\Enums\CourtClosureStatus;
use App\Enums\OwnerSettlementEntryType;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtClosure;
use App\Models\CourtClosureBooking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\CourtClosureNotification;
use App\Settlements\OwnerSettlementLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourtClosureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-17 19:00:00', 'Asia/Manila'));
        Notification::fake();
        $this->enablePayMongo();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_preview_uses_exact_overlap_boundaries_without_changing_anything(): void
    {
        [$organization, $owner, , $venue, $resource] = $this->workspace();
        $before = $this->booking($organization, $venue, $resource, '18:00', '20:00');
        $overlapStart = $this->booking($organization, $venue, $resource, '19:00', '20:30');
        $inside = $this->booking($organization, $venue, $resource, '20:30', '21:30');
        $overlapEnd = $this->booking($organization, $venue, $resource, '21:30', '23:30');
        $after = $this->booking($organization, $venue, $resource, '23:00', '23:30');

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.court-closures.preview'), [
                'scope' => 'court',
                'resource_id' => $resource->getKey(),
                'starts_at' => '2026-09-17T20:00',
                'until_reopened' => false,
                'ends_at' => '2026-09-17T23:00',
                'reason' => 'The court flooded after heavy rain.',
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/CourtClosures/Create')
                ->where('impact.booking_count', 3)
                ->where('impact.bookings.0.reference', $overlapStart->reference)
                ->where('impact.bookings.1.reference', $inside->reference)
                ->where('impact.bookings.2.reference', $overlapEnd->reference));

        $this->assertDatabaseCount('court_closures', 0);
        $this->assertSame(BookingStatus::Confirmed, $before->refresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $after->refresh()->status);
    }

    public function test_owner_closure_immediately_blocks_inventory_cancels_bookings_and_awaits_platform_approval(): void
    {
        [$organization, $owner, $admin, $venue, $resource] = $this->workspace();
        $onlinePlayer = User::factory()->create();
        $online = $this->booking($organization, $venue, $resource, '20:00', '21:00', $onlinePlayer, PaymentMode::HostedCheckout, PaymentStatus::Paid);
        $onlinePayment = $online->payment;
        $walkInPlayer = User::factory()->create();
        $walkIn = $this->booking($organization, $venue, $resource, '21:00', '22:00', $walkInPlayer, PaymentMode::PayAtVenue, PaymentStatus::Paid);
        $pendingPlayer = User::factory()->create();
        $pending = $this->booking($organization, $venue, $resource, '22:00', '23:00', $pendingPlayer, PaymentMode::HostedCheckout, PaymentStatus::Pending);

        Http::preventStrayRequests();
        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.court-closures.store'), $this->closureData($organization, $owner, [
                'scope' => 'court',
                'resource_id' => $resource->getKey(),
                'starts_at' => '2026-09-17T19:50',
                'until_reopened' => true,
                'reason' => 'The reserve court was damaged and is unsafe.',
            ]))
            ->assertRedirect(route('owner.court-closures.index'));

        $closure = CourtClosure::query()->sole();
        $this->assertSame(CourtClosureStatus::Active, $closure->status);
        $this->assertSame(CourtClosureRefundStatus::AwaitingApproval, $closure->refund_status);
        $this->assertNull($closure->ends_at);
        $this->assertDatabaseHas('court_availability_blocks', [
            'court_closure_id' => $closure->getKey(),
            'resource_id' => $resource->getKey(),
            'ends_at' => null,
            'cancelled_at' => null,
        ]);
        $this->assertTrue(app(AvailabilityService::class)->hasConflict(
            $resource->getKey(),
            CarbonImmutable::parse('2026-09-18 20:00', 'Asia/Manila')->utc(),
            CarbonImmutable::parse('2026-09-18 21:00', 'Asia/Manila')->utc(),
        ));
        $this->assertSame(BookingStatus::Cancelled, $online->refresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $walkIn->refresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $pending->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $onlinePayment->refresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $pending->payment->refresh()->status);
        $this->assertDatabaseHas('refund_requests', [
            'court_closure_id' => $closure->getKey(),
            'payment_id' => $onlinePayment->getKey(),
            'status' => RefundRequestStatus::Requested->value,
        ]);
        $this->assertDatabaseHas('court_closure_bookings', [
            'booking_id' => $walkIn->getKey(),
            'status' => CourtClosureBookingStatus::ManualRefundRequired->value,
        ]);
        Notification::assertSentTo($onlinePlayer, CourtClosureNotification::class, fn (CourtClosureNotification $notification): bool => $notification->kind === 'booking_cancelled_emergency');
        Notification::assertSentTo($walkInPlayer, CourtClosureNotification::class);
        Notification::assertSentTo($pendingPlayer, CourtClosureNotification::class);
        Notification::assertSentTo($admin, CourtClosureNotification::class, fn (CourtClosureNotification $notification): bool => $notification->kind === 'closure_refunds_awaiting_approval');
        Http::assertNothingSent();
        Http::allowStrayRequests(['http://localhost:5173/*']);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.court-closures.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/CourtClosures/Index')
                ->where('closures.0.reference', $closure->reference)
                ->where('closures.0.refund_status', CourtClosureRefundStatus::AwaitingApproval->value));
        $this->actingAs($admin)
            ->get(route('platform.court-closures.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/CourtClosures/Index')
                ->where('closures.0.can_approve', true));

        $refund = RefundRequest::query()->sole();
        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.refunds.approve', $refund))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->patch(route('owner.bookings.payment.update', $walkIn), [
                'status' => PaymentStatus::Refunded->value,
                'note' => 'Cash payment returned by venue staff.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('court_closure_bookings', [
            'booking_id' => $walkIn->getKey(),
            'status' => CourtClosureBookingStatus::Refunded->value,
        ]);
    }

    public function test_confirmation_is_rejected_when_the_affected_booking_set_changed_after_preview(): void
    {
        [$organization, $owner, , $venue, $resource] = $this->workspace();
        $data = [
            'scope' => 'court',
            'resource_id' => $resource->getKey(),
            'starts_at' => '2026-09-17T19:50',
            'until_reopened' => true,
            'reason' => 'The court is unsafe after a flood.',
        ];
        $previewed = $this->closureData($organization, $owner, $data);
        $booking = $this->booking($organization, $venue, $resource, '20:00', '21:00');

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.court-closures.store'), $previewed)
            ->assertSessionHasErrors('confirmed');

        $this->assertDatabaseCount('court_closures', 0);
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
    }

    public function test_platform_batch_approval_automatically_refunds_paymongo_and_reopen_does_not_reverse_cancellations(): void
    {
        [$organization, $owner, $admin, $venue, $resource] = $this->workspace();
        $player = User::factory()->create();
        $booking = $this->booking($organization, $venue, $resource, '20:00', '21:00', $player, PaymentMode::HostedCheckout, PaymentStatus::Paid);
        $payment = $booking->payment;
        app(OwnerSettlementLedger::class)->recordPaidPayment($payment);
        $closure = $this->createClosure($organization, $owner, $resource);
        $refund = RefundRequest::query()->sole();

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => Http::response([
                'data' => [
                    'id' => 'ref_closure_succeeded',
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

        $this->actingAs($admin)
            ->post(route('platform.court-closures.approve', $closure))
            ->assertRedirect();

        $this->assertSame(CourtClosureRefundStatus::Completed, $closure->refresh()->refund_status);
        $this->assertNotNull($closure->approved_at);
        $this->assertSame($admin->getKey(), $closure->approved_by_user_id);
        $this->assertSame(RefundRequestStatus::Refunded, $refund->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertSame(CourtClosureBookingStatus::Refunded, CourtClosureBooking::query()->sole()->status);
        $this->assertDatabaseHas('owner_settlement_entries', [
            'payment_id' => $payment->getKey(),
            'type' => OwnerSettlementEntryType::RefundAdjustment->value,
            'amount' => '-650.00',
        ]);
        Http::assertSentCount(1);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->patch(route('owner.court-closures.reopen', $closure))
            ->assertRedirect();

        $this->assertSame(CourtClosureStatus::Reopened, $closure->refresh()->status);
        $this->assertNotNull(CourtAvailabilityBlock::query()->sole()->cancelled_at);
        $this->assertFalse(app(AvailabilityService::class)->hasConflict(
            $resource->getKey(),
            CarbonImmutable::parse('2026-09-18 20:00', 'Asia/Manila')->utc(),
            CarbonImmutable::parse('2026-09-18 21:00', 'Asia/Manila')->utc(),
        ));
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
    }

    public function test_provider_failure_keeps_booking_cancelled_and_flags_batch_for_platform_attention(): void
    {
        [$organization, $owner, $admin, $venue, $resource] = $this->workspace();
        $booking = $this->booking($organization, $venue, $resource, '20:00', '21:00', User::factory()->create(), PaymentMode::HostedCheckout, PaymentStatus::Paid);
        $closure = $this->createClosure($organization, $owner, $resource);

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => Http::response([
                'errors' => [['code' => 'temporary_refund_error', 'detail' => 'Refund service is unavailable.']],
            ], 422),
        ]);

        $this->actingAs($admin)->post(route('platform.court-closures.approve', $closure))->assertRedirect();

        $this->assertSame(CourtClosureRefundStatus::Attention, $closure->refresh()->refund_status);
        $this->assertSame(CourtClosureBookingStatus::Failed, CourtClosureBooking::query()->sole()->status);
        $this->assertSame(RefundRequestStatus::Failed, RefundRequest::query()->sole()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment->refresh()->status);
    }

    public function test_another_organization_cannot_close_or_reopen_the_court(): void
    {
        [$organization, $owner, , , $resource] = $this->workspace();
        [$otherOrganization, $otherOwner] = $this->workspace();

        $this->actingAs($otherOwner)
            ->withSession(['tenant.organization_id' => $otherOrganization->getKey()])
            ->post(route('owner.court-closures.store'), [
                'scope' => 'court',
                'resource_id' => $resource->getKey(),
                'starts_at' => '2026-09-17T19:50',
                'until_reopened' => true,
                'reason' => 'Attempted cross-tenant closure.',
                'confirmed' => true,
                'confirmation_token' => '1.'.str_repeat('a', 64),
            ])
            ->assertSessionHasErrors('resource_id');

        $closure = $this->createClosure($organization, $owner, $resource);
        $this->actingAs($otherOwner)
            ->withSession(['tenant.organization_id' => $otherOrganization->getKey()])
            ->patch(route('owner.court-closures.reopen', $closure))
            ->assertNotFound();
    }

    /** @return array{Organization, User, User, Venue, CourtResource} */
    private function workspace(): array
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create();
        $resource = CourtResource::factory()->for($venue)->create();

        return [$organization, $owner, $admin, $venue, $resource];
    }

    private function booking(
        Organization $organization,
        Venue $venue,
        CourtResource $resource,
        string $start,
        string $end,
        ?User $player = null,
        PaymentMode $mode = PaymentMode::PayAtVenue,
        PaymentStatus $paymentStatus = PaymentStatus::Pending,
    ): Booking {
        $player ??= User::factory()->create();
        $booking = Booking::factory()
            ->for($organization)
            ->for($venue)
            ->for($resource, 'resource')
            ->for($player, 'player')
            ->create([
                'source' => BookingSource::Marketplace,
                'status' => BookingStatus::Confirmed,
                'payment_mode' => $mode,
                'payment_status' => $paymentStatus,
                'total_amount' => '650.00',
                'platform_service_fee_amount' => $mode === PaymentMode::HostedCheckout ? '25.00' : '0.00',
                'player_total_amount' => $mode === PaymentMode::HostedCheckout ? '675.00' : '650.00',
                'start_at' => CarbonImmutable::parse("2026-09-17 {$start}", 'Asia/Manila')->utc(),
                'end_at' => CarbonImmutable::parse("2026-09-17 {$end}", 'Asia/Manila')->utc(),
                'timezone' => 'Asia/Manila',
            ]);
        Payment::factory()->for($booking)->create([
            'organization_id' => $organization->getKey(),
            'created_by_user_id' => $player->getKey(),
            'provider' => $mode === PaymentMode::HostedCheckout ? 'paymongo' : 'manual',
            'mode' => $mode,
            'status' => $paymentStatus,
            'amount' => $mode === PaymentMode::HostedCheckout ? '675.00' : '650.00',
            'venue_amount' => '650.00',
            'platform_service_fee_amount' => $mode === PaymentMode::HostedCheckout ? '25.00' : '0.00',
            'provider_reference' => $mode === PaymentMode::HostedCheckout ? 'cs_closure_'.uniqid() : null,
            'provider_payment_reference' => $mode === PaymentMode::HostedCheckout ? 'pay_closure_'.uniqid() : null,
            'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
        ]);

        return $booking->refresh()->load('payment');
    }

    private function createClosure(Organization $organization, User $owner, CourtResource $resource): CourtClosure
    {
        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.court-closures.store'), $this->closureData($organization, $owner, [
                'scope' => 'court',
                'resource_id' => $resource->getKey(),
                'starts_at' => '2026-09-17T19:50',
                'until_reopened' => true,
                'reason' => 'The court flooded and is unsafe to use.',
            ]))
            ->assertRedirect(route('owner.court-closures.index'));

        return CourtClosure::query()->where('organization_id', $organization->getKey())->sole();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function closureData(Organization $organization, User $owner, array $data): array
    {
        $impact = app(CourtClosureImpact::class)->inspect($organization, $data);

        return [
            ...$data,
            'confirmed' => true,
            'confirmation_token' => app(CourtClosureConfirmation::class)->issue(
                $organization,
                $owner,
                $data,
                $impact,
            ),
        ];
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
