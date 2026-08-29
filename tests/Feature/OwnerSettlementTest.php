<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\MembershipRole;
use App\Enums\OwnerPayoutMethod;
use App\Enums\OwnerPayoutStatus;
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
use App\Payments\ApplyPaymentTransition;
use App\Settlements\OwnerSettlementLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['settlements.availability_delay_days' => 0]);
        config(['settlements.minimum_request_amount_centavos' => 50000]);
    }

    public function test_verified_online_payment_creates_only_the_court_price_as_owner_earnings(): void
    {
        [$organization, , , $booking, $payment] = $this->setupOnlinePayment();

        $this->pay($booking, $payment);

        $entry = OwnerSettlementEntry::query()->sole();
        $this->assertSame($organization->getKey(), $entry->organization_id);
        $this->assertSame(OwnerSettlementEntryType::BookingPayment, $entry->type);
        $this->assertSame('650.00', $entry->amount);
        $this->assertSame($payment->getKey(), $entry->payment_id);
        $this->assertDatabaseCount('owner_settlement_entries', 1);

        // Replaying the ledger hook remains idempotent by its immutable source key.
        app(OwnerSettlementLedger::class)->recordPaidPayment($payment->refresh());
        $this->assertDatabaseCount('owner_settlement_entries', 1);
    }

    public function test_pay_at_venue_payment_is_not_owed_again_by_finacourt(): void
    {
        [, , , $booking, $payment] = $this->setupOnlinePayment([
            'provider' => 'manual',
            'mode' => PaymentMode::PayAtVenue,
        ]);

        $this->pay($booking, $payment);

        $this->assertDatabaseCount('owner_settlement_entries', 0);
    }

    public function test_owner_cannot_manually_mark_a_hosted_checkout_payment_as_paid(): void
    {
        [, $owner, , $booking, $payment] = $this->setupOnlinePayment();

        $this->actingAs($owner)->patch(route('owner.bookings.payment.update', $booking), [
            'status' => PaymentStatus::Paid->value,
        ])->assertSessionHasErrors('payment');

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('owner_settlement_entries', 0);
    }

    public function test_refund_adds_a_separate_negative_entry_without_rewriting_original_earnings(): void
    {
        [, , , $booking, $payment] = $this->setupOnlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->pay($booking, $payment);

        $this->actingAs($admin)->post(route('platform.payments.refunds.store', $payment), [
            'external_reference' => 'PAYMONGO-REFUND-1',
            'note' => 'Refund completed in the provider dashboard.',
        ])->assertRedirect();

        $entries = OwnerSettlementEntry::query()->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame('650.00', $entries[0]->amount);
        $this->assertSame('-650.00', $entries[1]->amount);
        $this->assertSame(OwnerSettlementEntryType::RefundAdjustment, $entries[1]->type);
        $this->assertSame('0.00', number_format((float) $entries->sum('amount'), 2, '.', ''));
        $transition = $payment->refresh()->transitions()->where('source', 'platform_external_refund')->firstOrFail();
        $this->assertSame('PAYMONGO-REFUND-1', $transition->metadata['external_refund_reference']);
    }

    public function test_only_the_account_owner_can_save_encrypted_payout_details_or_view_earnings(): void
    {
        [$organization, $owner] = $this->setupOrganization();
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($organization)->create(['role' => MembershipRole::Staff]);

        $this->actingAs($owner)->put(route('owner.settlements.profile.update'), [
            'method' => OwnerPayoutMethod::Gcash->value,
            'account_name' => 'Court Owner',
            'mobile_number' => '09171234567',
            'is_active' => '1',
        ])->assertRedirect();

        $profile = OwnerPayoutProfile::query()->sole();
        $this->assertSame('09171234567', $profile->details['mobile_number']);
        $raw = DB::table('owner_payout_profiles')->value('details');
        $this->assertStringNotContainsString('09171234567', $raw);

        $this->actingAs($owner)->get(route('owner.settlements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Settlements/Index')
                ->where('profile.summary', 'GCash · ending 4567'));

        $this->actingAs($staff)->get(route('owner.settlements.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('owner.settlements.profile.update'), [
            'method' => OwnerPayoutMethod::Gcash->value,
            'account_name' => 'Wrong Person',
            'mobile_number' => '09999999999',
        ])->assertForbidden();
    }

    public function test_platform_can_prepare_approve_and_record_a_manual_owner_payout(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->setupOnlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $this->actingAs($admin)->post(route('platform.owner-payouts.store'), [
            'organization_id' => $organization->getKey(),
            'currency' => 'PHP',
            'period_ended_at' => now()->toDateString(),
        ])->assertRedirect();

        $payout = OwnerPayout::query()->sole();
        $this->assertSame(OwnerPayoutStatus::Pending, $payout->status);
        $this->assertSame('650.00', $payout->amount);
        $this->assertSame('09171234567', $payout->destination_snapshot['details']['mobile_number']);
        $this->assertSame($payout->getKey(), OwnerSettlementEntry::query()->sole()->owner_payout_id);

        $organization->payoutProfile->update(['details' => ['mobile_number' => '09990000000']]);
        $this->assertSame('09171234567', $payout->refresh()->destination_snapshot['details']['mobile_number']);

        $this->actingAs($admin)->post(route('platform.owner-payouts.approve', $payout))->assertRedirect();
        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $payout), [
            'external_reference' => 'GCASH-TRANSFER-123',
        ])->assertRedirect();

        $payout->refresh();
        $this->assertSame(OwnerPayoutStatus::Sent, $payout->status);
        $this->assertSame('GCASH-TRANSFER-123', $payout->external_reference);
        $this->assertCount(3, $payout->events);

        $this->actingAs($owner)->get(route('owner.settlements.payouts.statement', $payout))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_owner_can_request_the_server_calculated_ready_balance_for_the_current_tenant(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->setupOnlinePayment();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $this->actingAs($owner)->get(route('owner.settlements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payoutRequest.can_request', true)
                ->where('payoutRequest.minimum_amount', '500.00')
                ->where('summary.ready', '650.00'));

        $this->actingAs($owner)->post(route('owner.settlements.request'), [
            'organization_id' => $otherOrganization->getKey(),
            'amount' => '999999.00',
            'currency' => 'USD',
        ])->assertRedirect();

        $payout = OwnerPayout::query()->sole();
        $this->assertSame($organization->getKey(), $payout->organization_id);
        $this->assertSame('650.00', $payout->amount);
        $this->assertSame('PHP', $payout->currency);
        $this->assertSame(OwnerPayoutStatus::Pending, $payout->status);
        $this->assertSame($owner->getKey(), $payout->requested_by_user_id);
        $this->assertNotNull($payout->requested_at);
        $this->assertSame('09171234567', $payout->destination_snapshot['details']['mobile_number']);
        $this->assertSame($payout->getKey(), OwnerSettlementEntry::query()->sole()->owner_payout_id);
        $this->assertDatabaseHas('owner_payout_events', [
            'owner_payout_id' => $payout->getKey(),
            'actor_user_id' => $owner->getKey(),
            'action' => 'requested',
        ]);

        $this->actingAs($admin)->get(route('platform.owner-payouts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payouts.0.requested_by_owner', true)
                ->where('payouts.0.requested_by', $owner->name));

        $this->actingAs($admin)->post(route('platform.owner-payouts.approve', $payout))->assertRedirect();
        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $payout), [
            'external_reference' => 'GCASH-PAYMONGO-EARNINGS-1',
        ])->assertRedirect();
        $this->assertSame(OwnerPayoutStatus::Sent, $payout->refresh()->status);
    }

    public function test_owner_request_requires_active_details_and_the_configured_minimum(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->setupOnlinePayment();
        $this->pay($booking, $payment);

        $this->actingAs($owner)->post(route('owner.settlements.request'))
            ->assertSessionHasErrors('payout');
        $this->assertDatabaseCount('owner_payouts', 0);

        $this->saveProfile($organization, $owner);
        config(['settlements.minimum_request_amount_centavos' => 70000]);

        $this->actingAs($owner)->post(route('owner.settlements.request'))
            ->assertSessionHasErrors('payout');
        $this->assertDatabaseCount('owner_payouts', 0);
        $this->assertNull(OwnerSettlementEntry::query()->sole()->owner_payout_id);
    }

    public function test_staff_and_duplicate_open_owner_payout_requests_are_rejected(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->setupOnlinePayment();
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($organization)->create(['role' => MembershipRole::Staff]);
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $this->actingAs($staff)->post(route('owner.settlements.request'))->assertForbidden();
        $this->actingAs($owner)->post(route('owner.settlements.request'))->assertRedirect();

        OwnerSettlementEntry::query()->create([
            'organization_id' => $organization->getKey(),
            'type' => OwnerSettlementEntryType::AdminAdjustment,
            'amount' => '500.00',
            'currency' => 'PHP',
            'source_key' => 'test:later-ready-entry',
            'description' => 'Later ready earnings',
            'occurred_at' => now(),
            'available_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('owner.settlements.request'))
            ->assertSessionHasErrors('payout');

        $this->assertDatabaseCount('owner_payouts', 1);
        $this->assertNull(OwnerSettlementEntry::query()->where('source_key', 'test:later-ready-entry')->value('owner_payout_id'));
    }

    public function test_failed_payout_releases_earnings_for_a_later_batch_and_admin_routes_are_protected(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->setupOnlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $ordinaryUser = User::factory()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);

        $this->actingAs($ordinaryUser)->get(route('platform.owner-payouts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('platform.owner-payouts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Settlements/Index')
                ->where('organizations.0.name', $organization->name));

        $this->actingAs($admin)->post(route('platform.owner-payouts.store'), [
            'organization_id' => $organization->getKey(),
            'currency' => 'PHP',
            'period_ended_at' => now()->toDateString(),
        ]);
        $payout = OwnerPayout::query()->sole();

        $this->actingAs($admin)->post(route('platform.owner-payouts.fail', $payout), [
            'reason' => 'Recipient account rejected the transfer.',
        ])->assertRedirect();

        $this->assertSame(OwnerPayoutStatus::Failed, $payout->refresh()->status);
        $this->assertNull(OwnerSettlementEntry::query()->sole()->owner_payout_id);

        $this->actingAs($admin)->post(route('platform.owner-payouts.store'), [
            'organization_id' => $organization->getKey(),
            'currency' => 'PHP',
            'period_ended_at' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertDatabaseCount('owner_payouts', 2);
    }

    public function test_refund_before_transfer_recalculates_and_cancels_an_empty_payout(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->setupOnlinePayment();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);
        $this->actingAs($admin)->post(route('platform.owner-payouts.store'), [
            'organization_id' => $organization->getKey(),
            'currency' => 'PHP',
            'period_ended_at' => now()->toDateString(),
        ])->assertRedirect();
        $payout = OwnerPayout::query()->sole();

        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $payment->refresh(),
            $booking->refresh(),
            PaymentStatus::Refunded,
            'test_refund',
            $admin,
        ));

        $this->assertSame(OwnerPayoutStatus::Cancelled, $payout->refresh()->status);
        $this->assertSame(2, OwnerSettlementEntry::query()->whereNull('owner_payout_id')->count());
        $this->assertSame('0.00', number_format((float) OwnerSettlementEntry::query()->sum('amount'), 2, '.', ''));
    }

    public function test_returned_transfer_is_re_owed_and_cross_tenant_statement_access_is_blocked(): void
    {
        [$organization, $owner, , $booking, $payment] = $this->setupOnlinePayment();
        [$otherOrganization, $otherOwner] = $this->setupOrganization();
        $admin = User::factory()->platformAdmin()->create();
        $this->saveProfile($organization, $owner);
        $this->pay($booking, $payment);
        $this->actingAs($admin)->post(route('platform.owner-payouts.store'), [
            'organization_id' => $organization->getKey(),
            'currency' => 'PHP',
            'period_ended_at' => now()->toDateString(),
        ]);
        $payout = OwnerPayout::query()->sole();
        $this->actingAs($admin)->post(route('platform.owner-payouts.approve', $payout));
        $this->actingAs($admin)->post(route('platform.owner-payouts.send', $payout), ['external_reference' => 'BANK-1']);
        $this->actingAs($admin)->post(route('platform.owner-payouts.reverse', $payout), ['reason' => 'Bank returned the transfer.']);

        $this->assertSame(OwnerPayoutStatus::Reversed, $payout->refresh()->status);
        $reowed = OwnerSettlementEntry::query()->where('type', OwnerSettlementEntryType::PayoutReversal)->sole();
        $this->assertSame('650.00', $reowed->amount);
        $this->assertNull($reowed->owner_payout_id);

        $this->actingAs($otherOwner)
            ->withSession(['tenant.organization_id' => $otherOrganization->getKey()])
            ->get(route('owner.settlements.payouts.statement', $payout))
            ->assertNotFound();
    }

    /** @return array{Organization, User} */
    private function setupOrganization(): array
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$organization, $owner];
    }

    /** @param array<string, mixed> $paymentAttributes
     * @return array{Organization, User, Venue, Booking, Payment}
     */
    private function setupOnlinePayment(array $paymentAttributes = []): array
    {
        [$organization, $owner] = $this->setupOrganization();
        $venue = Venue::factory()->for($organization)->create();
        $resource = CourtResource::factory()->for($venue)->create();
        $booking = Booking::factory()->for($organization)->for($venue)->for($resource, 'resource')->hold()->create([
            'source' => BookingSource::Marketplace,
            'total_amount' => '650.00',
            'platform_service_fee_amount' => '25.00',
            'player_total_amount' => '675.00',
            'currency' => 'PHP',
            'payment_mode' => PaymentMode::HostedCheckout,
            'payment_status' => PaymentStatus::Pending,
        ]);
        $payment = Payment::factory()->for($booking)->create([
            'organization_id' => $organization->getKey(),
            'provider' => 'paymongo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Pending,
            'amount' => '675.00',
            'venue_amount' => '650.00',
            'platform_service_fee_amount' => '25.00',
            ...$paymentAttributes,
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
            externalEventId: 'paymongo:test-'.$payment->getKey(),
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
