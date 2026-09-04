<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\OwnerPayoutMethod;
use App\Enums\OwnerPayoutStatus;
use App\Enums\OwnerPayoutType;
use App\Enums\OwnerSettlementEntryType;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutProfile;
use App\Models\OwnerSettlementEntry;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\OwnerPayoutNotification;
use App\Notifications\PlatformOwnerPayoutRequestedNotification;
use App\Payments\ApplyPaymentTransition;
use App\Payouts\Contracts\PayoutProvider;
use App\Settlements\OwnerBalanceService;
use App\Settlements\OwnerPayoutWorkflow;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerPayoutPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'settlements.enabled' => true,
            'settlements.currency' => 'PHP',
            'settlements.timezone' => 'Asia/Manila',
            'settlements.clearing_hours' => 24,
            'settlements.provider' => 'manual',
            'settlements.transfer_fee_centavos' => 1000,
            'settlements.scheduled.enabled' => true,
            'settlements.scheduled.minimum_centavos' => 100,
            'settlements.early.enabled' => true,
            'settlements.early.minimum_centavos' => 100,
            'settlements.early.fee_payer' => 'owner',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_paid_future_booking_stays_pending_until_completion_and_clearing(): void
    {
        $paidAt = CarbonImmutable::parse('2026-09-03 01:00:00', 'UTC');
        CarbonImmutable::setTestNow($paidAt);
        [$organization, , , $booking, $payment] = $this->onlinePayment(
            startAt: CarbonImmutable::parse('2026-09-10 08:00:00', 'UTC'),
            endAt: CarbonImmutable::parse('2026-09-10 09:00:00', 'UTC'),
        );

        $this->pay($booking, $payment);

        $entry = OwnerSettlementEntry::query()->sole();
        $this->assertSame('2026-09-11 09:00:00', $entry->available_at->format('Y-m-d H:i:s'));
        $this->assertSame([
            'pending' => 65000,
            'available' => 0,
            'processing' => 0,
            'paid' => 0,
        ], app(OwnerBalanceService::class)->balances($organization->getKey(), at: $paidAt));

        $justBefore = CarbonImmutable::parse('2026-09-11 08:59:59', 'UTC');
        $this->assertSame(65000, app(OwnerBalanceService::class)->balances($organization->getKey(), at: $justBefore)['pending']);

        $cleared = CarbonImmutable::parse('2026-09-11 09:00:00', 'UTC');
        $this->assertSame([
            'pending' => 0,
            'available' => 65000,
            'processing' => 0,
            'paid' => 0,
        ], app(OwnerBalanceService::class)->balances($organization->getKey(), at: $cleared));
    }

    public function test_owner_balance_uses_only_court_price_and_excludes_the_platform_fee(): void
    {
        config(['settlements.clearing_hours' => 0]);
        [$organization, , , $booking, $payment] = $this->onlinePayment(
            venueAmount: '500.00',
            platformFee: '50.00',
        );

        $this->pay($booking, $payment);

        $this->assertSame('500.00', OwnerSettlementEntry::query()->sole()->amount);
        $this->assertSame(50000, app(OwnerBalanceService::class)->balances($organization->getKey())['available']);
        $this->assertSame('50.00', $payment->refresh()->platform_service_fee_amount);
    }

    public function test_refunded_booking_is_not_available_for_payout(): void
    {
        config(['settlements.clearing_hours' => 0]);
        [$organization, , , $booking, $payment] = $this->onlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->pay($booking, $payment);
        $this->refund($booking, $payment, $admin);

        $balances = app(OwnerBalanceService::class)->balances($organization->getKey());

        $this->assertSame(0, $balances['pending']);
        $this->assertSame(0, $balances['available']);
        $this->assertDatabaseHas('owner_settlement_entries', [
            'payment_id' => $payment->getKey(),
            'type' => OwnerSettlementEntryType::RefundAdjustment->value,
            'amount' => '-650.00',
        ]);
    }

    public function test_scheduler_creates_one_free_payout_and_reserves_each_earning_once(): void
    {
        $cycle = CarbonImmutable::parse('2026-09-15 00:30:00', 'Asia/Manila');
        CarbonImmutable::setTestNow($cycle);
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $this->artisan('owners:payout-scheduled', ['--date' => '2026-09-15'])
            ->expectsOutput('Queued 1 free scheduled owner payout(s) for 2026-09-15.')
            ->assertSuccessful();
        $this->artisan('owners:payout-scheduled', ['--date' => '2026-09-15'])
            ->expectsOutput('Queued 0 free scheduled owner payout(s) for 2026-09-15.')
            ->assertSuccessful();

        $payout = OwnerPayout::query()->sole();
        $this->assertSame(OwnerPayoutType::Scheduled, $payout->payout_type);
        $this->assertSame(OwnerPayoutStatus::Pending, $payout->status);
        $this->assertSame('650.00', $payout->gross_amount);
        $this->assertSame('10.00', $payout->payout_fee);
        $this->assertSame('650.00', $payout->net_amount);
        $this->assertSame('platform', $payout->fee_payer);
        $this->assertSame('manual', $payout->provider);
        $this->assertSame('2026-09-15', $payout->scheduled_for->toDateString());
        $this->assertNotNull($payout->cycle_key);
        $this->assertSame($payout->getKey(), OwnerSettlementEntry::query()->sole()->owner_payout_id);
        $this->assertSame(0, app(OwnerBalanceService::class)->balances($organization->getKey())['available']);
        $this->assertSame(65000, app(OwnerBalanceService::class)->balances($organization->getKey())['processing']);
    }

    public function test_scheduler_skips_no_balance_future_balance_and_below_minimum(): void
    {
        $cycle = CarbonImmutable::parse('2026-09-15 00:30:00', 'Asia/Manila');
        CarbonImmutable::setTestNow($cycle);
        $organizationWithoutFunds = Organization::factory()->create();
        $ownerWithoutFunds = User::factory()->create();
        Membership::factory()->owner()->for($ownerWithoutFunds)->for($organizationWithoutFunds)->create();
        $this->saveProfile($organizationWithoutFunds, $ownerWithoutFunds);

        [$futureOrganization, $futureOwner, , $futureBooking, $futurePayment] = $this->onlinePayment(
            startAt: $cycle->addWeek()->utc(),
            endAt: $cycle->addWeek()->addHour()->utc(),
        );
        $this->saveProfile($futureOrganization, $futureOwner);
        $this->pay($futureBooking, $futurePayment);

        config(['settlements.clearing_hours' => 0, 'settlements.scheduled.minimum_centavos' => 70000]);
        [$smallOrganization, $smallOwner, , $smallBooking, $smallPayment] = $this->onlinePayment();
        $this->saveProfile($smallOrganization, $smallOwner);
        $this->pay($smallBooking, $smallPayment);

        $this->artisan('owners:payout-scheduled', ['--date' => '2026-09-15'])
            ->expectsOutput('Queued 0 free scheduled owner payout(s) for 2026-09-15.')
            ->assertSuccessful();

        $this->assertDatabaseCount('owner_payouts', 0);
        $this->assertSame(65000, app(OwnerBalanceService::class)->balances($futureOrganization->getKey())['pending']);
        $this->assertSame(65000, app(OwnerBalanceService::class)->balances($smallOrganization->getKey())['available']);
    }

    public function test_early_payout_fee_and_amount_are_calculated_only_by_the_server(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 10:00:00', 'Asia/Manila'));
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $this->actingAs($owner)->post(route('owner.settlements.request'), [
            'confirmed' => true,
            'amount' => '1.00',
            'fee_amount' => '0.00',
            'net_amount' => '999999.00',
            'organization_id' => Organization::factory()->create()->getKey(),
        ])->assertRedirect();

        $payout = OwnerPayout::query()->sole();
        $this->assertSame($organization->getKey(), $payout->organization_id);
        $this->assertSame(OwnerPayoutType::Early, $payout->payout_type);
        $this->assertSame('650.00', $payout->gross_amount);
        $this->assertSame('10.00', $payout->payout_fee);
        $this->assertSame('640.00', $payout->net_amount);
        $this->assertSame('owner', $payout->fee_payer);

        $this->actingAs($owner)->get(route('owner.settlements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('schedule.next_date_label', 'September 15, 2026')
                ->where('schedule.minimum_amount', '1.00')
                ->where('earlyPayout.open.reference', $payout->reference));
    }

    public function test_pending_earnings_cannot_be_requested_early(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment(
            startAt: now()->addDay(),
            endAt: now()->addDay()->addHour(),
        );
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $this->actingAs($owner)->post(route('owner.settlements.request'), ['confirmed' => true])
            ->assertSessionHasErrors('payout');

        $this->assertDatabaseCount('owner_payouts', 0);
        $this->assertSame(65000, app(OwnerBalanceService::class)->balances($organization->getKey())['pending']);
    }

    public function test_failed_processing_releases_funds_and_processing_retry_is_idempotent(): void
    {
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);
        $payout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);

        app(OwnerPayoutWorkflow::class)->startProcessing($payout, $admin);
        app(OwnerPayoutWorkflow::class)->startProcessing($payout->refresh(), $admin);
        $this->assertSame(1, $payout->events()->where('action', 'processing')->count());

        app(OwnerPayoutWorkflow::class)->markFailed($payout->refresh(), $admin, 'Recipient rejected', 'recipient_rejected');

        $this->assertSame(OwnerPayoutStatus::Failed, $payout->refresh()->status);
        $this->assertSame('recipient_rejected', $payout->failure_code);
        $this->assertNull(OwnerSettlementEntry::query()->sole()->owner_payout_id);
        $this->assertSame(65000, app(OwnerBalanceService::class)->balances($organization->getKey())['available']);

        $retry = app(OwnerPayoutWorkflow::class)->request($organization, $owner);
        $this->assertNotSame($payout->getKey(), $retry->getKey());
        $this->assertDatabaseCount('owner_payouts', 2);
        $this->assertDatabaseCount('owner_settlement_entries', 1);
    }

    public function test_manual_mark_paid_requires_exact_reconciliation_and_is_idempotent(): void
    {
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);
        $payout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);
        app(OwnerPayoutWorkflow::class)->startProcessing($payout, $admin);

        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $payout), [
            'external_reference' => 'GCASH-RECON-001',
            'paid_amount' => '650.00',
            'paid_at' => now('Asia/Manila')->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('paid_amount');

        $payload = [
            'external_reference' => 'GCASH-RECON-001',
            'paid_amount' => '640.00',
            'paid_at' => now('Asia/Manila')->format('Y-m-d\TH:i'),
        ];
        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $payout), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $payout), $payload)->assertRedirect();

        $this->assertSame(OwnerPayoutStatus::Paid, $payout->refresh()->status);
        $this->assertSame('640.00', $payout->paid_amount);
        $this->assertSame('GCASH-RECON-001', $payout->external_reference);
        $this->assertSame(1, $payout->events()->where('action', 'admin_marked_paid')->count());
        $balances = app(OwnerBalanceService::class)->balances($organization->getKey());
        $this->assertSame(0, $balances['available']);
        $this->assertSame(0, $balances['processing']);
        $this->assertSame(64000, $balances['paid']);

        OwnerSettlementEntry::query()->create([
            'organization_id' => $organization->getKey(),
            'type' => OwnerSettlementEntryType::AdminAdjustment,
            'amount' => '200.00',
            'currency' => 'PHP',
            'source_key' => 'test:second-payout',
            'description' => 'Second eligible amount',
            'occurred_at' => now(),
            'available_at' => now(),
        ]);
        $secondPayout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);
        app(OwnerPayoutWorkflow::class)->startProcessing($secondPayout, $admin);

        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $secondPayout), [
            'external_reference' => 'GCASH-RECON-001',
            'paid_amount' => '190.00',
            'paid_at' => now('Asia/Manila')->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('external_reference');
        $this->assertSame(OwnerPayoutStatus::Processing, $secondPayout->refresh()->status);
    }

    public function test_refund_during_processing_blocks_paid_status_then_failure_releases_no_refunded_money(): void
    {
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);
        $payout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);
        app(OwnerPayoutWorkflow::class)->startProcessing($payout, $admin);

        $this->refund($booking, $payment, $admin);

        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $payout), [
            'external_reference' => 'MUST-NOT-BE-PAID',
            'paid_amount' => '640.00',
            'paid_at' => now('Asia/Manila')->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('payout');
        $this->assertSame(OwnerPayoutStatus::Processing, $payout->refresh()->status);

        app(OwnerPayoutWorkflow::class)->markFailed($payout, $admin, 'Refunded before transfer');
        $balances = app(OwnerBalanceService::class)->balances($organization->getKey());
        $this->assertSame(0, $balances['available']);
        $this->assertSame(0, $balances['processing']);
        $this->assertSame(2, OwnerSettlementEntry::query()->whereNull('owner_payout_id')->count());
    }

    public function test_paid_earning_is_not_available_again_and_later_refund_is_an_auditable_recovery(): void
    {
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);
        $payout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);
        app(OwnerPayoutWorkflow::class)->startProcessing($payout, $admin);
        app(OwnerPayoutWorkflow::class)->markPaid($payout, $admin, 'BANK-PAID-001', '640.00', now());

        $this->assertSame(0, app(OwnerBalanceService::class)->balances($organization->getKey())['available']);
        $this->refund($booking, $payment, $admin);

        $this->assertSame(-65000, app(OwnerBalanceService::class)->balances($organization->getKey())['available']);
        $recovery = OwnerSettlementEntry::query()->where('type', OwnerSettlementEntryType::RefundAdjustment)->sole();
        $this->assertSame('-650.00', $recovery->amount);
        $this->assertNull($recovery->owner_payout_id);
        $this->assertSame('full_venue_amount', $recovery->metadata['allocation']);
    }

    public function test_player_cannot_request_and_other_tenant_cannot_view_a_payout(): void
    {
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        [$otherOrganization, $otherOwner] = $this->organizationAndOwner();
        $player = User::factory()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);
        $payout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);

        $this->actingAs($player)->post(route('owner.settlements.request'), ['confirmed' => true])->assertForbidden();
        $this->actingAs($otherOwner)
            ->withSession(['tenant.organization_id' => $otherOrganization->getKey()])
            ->get(route('owner.settlements.payouts.statement', $payout))
            ->assertNotFound();
    }

    public function test_manual_provider_never_claims_automatic_transfer_support(): void
    {
        $provider = app(PayoutProvider::class);

        $this->assertSame('manual', $provider->key());
        $this->assertFalse($provider->supportsAutomaticTransfers());
    }

    public function test_unimplemented_payout_provider_configuration_fails_closed(): void
    {
        config(['settlements.provider' => 'paymongo']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported owner payout provider [paymongo]');

        app(PayoutProvider::class);
    }

    public function test_owner_is_notified_through_the_payout_lifecycle(): void
    {
        Notification::fake();
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $paidPayout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);
        app(OwnerPayoutWorkflow::class)->approve($paidPayout, $admin);
        app(OwnerPayoutWorkflow::class)->startProcessing($paidPayout, $admin);
        app(OwnerPayoutWorkflow::class)->startProcessing($paidPayout, $admin);
        app(OwnerPayoutWorkflow::class)->markPaid($paidPayout, $admin, 'NOTICE-PAID-1', '640.00', now());

        OwnerSettlementEntry::query()->create([
            'organization_id' => $organization->getKey(),
            'type' => OwnerSettlementEntryType::AdminAdjustment,
            'amount' => '200.00',
            'currency' => 'PHP',
            'source_key' => 'test:notification-failure',
            'description' => 'Notification failure case',
            'occurred_at' => now(),
            'available_at' => now(),
        ]);
        $failedPayout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);
        app(OwnerPayoutWorkflow::class)->markFailed($failedPayout, $admin, 'Test failure');

        Notification::assertSentTo(
            $owner,
            fn (OwnerPayoutNotification $notification): bool => $notification->kind === 'owner_payout_queued',
        );
        Notification::assertSentTo(
            $owner,
            fn (OwnerPayoutNotification $notification): bool => $notification->kind === 'owner_payout_approved',
        );
        Notification::assertSentTo(
            $owner,
            fn (OwnerPayoutNotification $notification): bool => $notification->kind === 'owner_payout_processing',
        );
        Notification::assertSentTo(
            $owner,
            fn (OwnerPayoutNotification $notification): bool => $notification->kind === 'owner_payout_paid',
        );
        Notification::assertSentTo(
            $owner,
            fn (OwnerPayoutNotification $notification): bool => $notification->kind === 'owner_payout_failed',
        );
        $this->assertCount(
            1,
            Notification::sent($owner, OwnerPayoutNotification::class)
                ->where('kind', 'owner_payout_processing'),
        );
    }

    public function test_platform_administrators_are_emailed_when_an_owner_requests_an_early_payout(): void
    {
        Notification::fake();
        config(['settlements.clearing_hours' => 0]);
        [$organization, $owner, , $booking, $payment] = $this->onlinePayment();
        $administrator = User::factory()->platformAdmin()->create();
        $secondAdministrator = User::factory()->platformAdmin()->create();
        $ordinaryUser = User::factory()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $payout = app(OwnerPayoutWorkflow::class)->request($organization, $owner);

        foreach ([$administrator, $secondAdministrator] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                function (PlatformOwnerPayoutRequestedNotification $notification) use ($organization, $owner, $payout, $recipient): bool {
                    return in_array('mail', $notification->via($recipient), true)
                        && $notification->organizationName === $organization->name
                        && $notification->requesterName === $owner->name
                        && $notification->requesterEmail === $owner->email
                        && $notification->payoutReference === $payout->reference
                        && $notification->grossAmount === '650.00'
                        && $notification->feeAmount === '10.00'
                        && $notification->netAmount === '640.00';
                },
            );
        }

        Notification::assertNotSentTo($ordinaryUser, PlatformOwnerPayoutRequestedNotification::class);
    }

    public function test_money_conversion_is_exact_to_the_centavo(): void
    {
        $this->assertSame(50000, Money::cents('500.00'));
        $this->assertSame(10, Money::cents('0.10'));
        $this->assertSame(-65000, Money::cents('-650.00'));
        $this->assertSame('3390.00', Money::format(339000));
    }

    /** @return array{Organization, User} */
    private function organizationAndOwner(): array
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$organization, $owner];
    }

    /** @return array{Organization, User, Venue, Booking, Payment} */
    private function onlinePayment(
        ?CarbonInterface $startAt = null,
        ?CarbonInterface $endAt = null,
        string $venueAmount = '650.00',
        string $platformFee = '25.00',
    ): array {
        [$organization, $owner] = $this->organizationAndOwner();
        $venue = Venue::factory()->for($organization)->create();
        $resource = CourtResource::factory()->for($venue)->create();
        $startAt ??= CarbonImmutable::instance(now())->subHours(2);
        $endAt ??= CarbonImmutable::instance(now())->subHour();
        $playerTotal = Money::format(Money::cents($venueAmount) + Money::cents($platformFee));
        $booking = Booking::factory()->for($organization)->for($venue)->for($resource, 'resource')->hold()->create([
            'source' => BookingSource::Marketplace,
            'total_amount' => $venueAmount,
            'platform_service_fee_amount' => $platformFee,
            'player_total_amount' => $playerTotal,
            'currency' => 'PHP',
            'payment_mode' => PaymentMode::HostedCheckout,
            'payment_status' => PaymentStatus::Pending,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
        $payment = Payment::factory()->for($booking)->create([
            'organization_id' => $organization->getKey(),
            'provider' => 'paymongo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Pending,
            'amount' => $playerTotal,
            'venue_amount' => $venueAmount,
            'platform_service_fee_amount' => $platformFee,
        ]);

        return [$organization, $owner, $venue, $booking, $payment];
    }

    private function pay(Booking $booking, Payment $payment): void
    {
        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $payment,
            $booking,
            PaymentStatus::Paid,
            'verified_provider_test',
            externalEventId: 'paymongo:paid:'.$payment->getKey(),
        ));
    }

    private function refund(Booking $booking, Payment $payment, User $admin): void
    {
        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $payment->refresh(),
            $booking->refresh(),
            PaymentStatus::Refunded,
            'verified_refund_test',
            $admin,
            externalEventId: 'paymongo:refund:'.$payment->getKey(),
        ));
    }

    private function saveProfile(Organization $organization, User $owner): OwnerPayoutProfile
    {
        return OwnerPayoutProfile::query()->create([
            'organization_id' => $organization->getKey(),
            'method' => OwnerPayoutMethod::Gcash,
            'account_name' => 'Court Owner',
            'details' => ['mobile_number' => '09171234567'],
            'is_active' => true,
            'updated_by_user_id' => $owner->getKey(),
        ]);
    }
}
