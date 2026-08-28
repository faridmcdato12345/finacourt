<?php

namespace Tests\Feature;

use App\Enums\CommissionEntryStatus;
use App\Enums\LeadConflictStatus;
use App\Enums\MembershipRole;
use App\Enums\PartnerPayoutStatus;
use App\Enums\SalesLeadStatus;
use App\Enums\SalesPartnerStatus;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SalesLead;
use App\Models\SalesPartnerAttribution;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Models\Venue;
use App\SalesPartners\CommissionLedger;
use App\SalesPartners\LeadManager;
use App\SalesPartners\PartnerPayoutService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesPartnerModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_creates_isolated_partner_identity_with_encrypted_payout_details(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->create(['email' => 'rep@example.com']);

        $this->actingAs($admin)->post(route('platform.sales.partners.store'), [
            'email' => $user->email,
            'payout_details' => 'GCash 09170000000',
        ])->assertRedirect();

        $partner = $user->salesPartnerProfile()->firstOrFail();
        $this->assertSame(SalesPartnerStatus::Active, $partner->status);
        $this->assertSame(['instructions' => 'GCash 09170000000'], $partner->payout_details);
        $raw = DB::table('sales_partner_profiles')->where('id', $partner->getKey())->value('payout_details');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('09170000000', $raw);
        CommissionEntry::factory()->for($partner, 'partner')->create(['amount' => '123.00']);

        $this->actingAs($user)->get(route('partner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partner/Dashboard')
                ->where('partner.referral_code', $partner->referral_code)
                ->where('metrics.pending', '123.00')
                ->missing('partner.payout_details'));
        $this->actingAs($user)->get(route('owner.dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('partner.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('platform.sales.index'))->assertForbidden();
    }

    public function test_lead_protection_duplicate_dispute_self_referral_and_expiry_rules(): void
    {
        [$userA, $partnerA] = $this->partner();
        [$userB, $partnerB] = $this->partner();
        $payload = $this->leadPayload();

        $this->actingAs($userA)->post(route('partner.leads.store'), $payload)->assertRedirect();
        $first = SalesLead::query()->firstOrFail();
        $this->assertTrue($first->isProtected());

        $this->actingAs($userA)->post(route('partner.leads.store'), $payload)
            ->assertSessionHasErrors('contact_value');

        $this->actingAs($userB)->post(route('partner.leads.store'), $payload)->assertRedirect();
        $disputed = SalesLead::query()->latest('id')->firstOrFail();
        $this->assertSame($partnerB->getKey(), $disputed->sales_partner_profile_id);
        $this->assertSame(LeadConflictStatus::Disputed, $disputed->conflict_status);
        $this->assertSame($first->getKey(), $disputed->duplicate_of_lead_id);
        $this->assertNull($disputed->protection_expires_at);

        $first->update(['protection_expires_at' => now('UTC')->subMinute()]);
        $this->actingAs($userB)->post(route('partner.leads.store'), [
            ...$payload,
            'contact_value' => 'different@example.com',
        ])->assertRedirect();
        $this->assertSame(LeadConflictStatus::Clear, SalesLead::query()->latest('id')->first()->conflict_status);

        $this->actingAs($userA)->post(route('partner.leads.store'), [
            ...$payload,
            'business_name' => 'Self Referral Courts',
            'contact_value' => $userA->email,
        ])->assertSessionHasErrors('contact_value');
    }

    public function test_suspended_partner_cannot_register_leads_or_issue_trusted_referrals(): void
    {
        [$user, $partner] = $this->partner(SalesPartnerStatus::Suspended);

        $this->get(route('partner.referral', $partner->referral_code))->assertNotFound();
        $this->get(route('partner.referral.qr', $partner->referral_code))->assertNotFound();
        $this->actingAs($user)->get(route('partner.dashboard'))->assertOk();
        $this->actingAs($user)->post(route('partner.leads.store'), $this->leadPayload())->assertForbidden();
    }

    public function test_raw_partner_marker_never_creates_commission_grade_referral_but_issued_link_does(): void
    {
        [, $partner] = $this->partner();

        $this->post(route('register', ['partner' => $partner->referral_code]), [
            'name' => 'Raw Owner',
            'email' => 'raw-owner@example.com',
            'organization_name' => 'Raw Marker Courts',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('owner.dashboard'));
        $this->assertDatabaseCount('sales_partner_attributions', 0);
        $this->post(route('logout'));

        $this->get(route('partner.referral', $partner->referral_code))->assertRedirect(route('register'));
        $this->post(route('register'), [
            'name' => 'Trusted Owner',
            'email' => 'trusted-owner@example.com',
            'organization_name' => 'Trusted Referral Courts',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('owner.dashboard'));

        $attribution = SalesPartnerAttribution::query()->firstOrFail();
        $this->assertSame($partner->getKey(), $attribution->sales_partner_profile_id);
        $this->assertSame($partner->referral_code, $attribution->referral_code_snapshot);
        $this->assertSame('referral_link', $attribution->source);
        $this->assertNotNull($attribution->lead);
    }

    public function test_assisted_onboarding_requires_real_owner_inventory_and_never_grants_rep_tenant_access(): void
    {
        [$rep, $partner] = $this->partner();
        $admin = User::factory()->platformAdmin()->create();
        [$owner, $organization, $venue] = $this->ownedVenue();
        $lead = SalesLead::factory()->for($partner, 'partner')->create([
            'status' => SalesLeadStatus::Onboarding,
            'conflict_status' => LeadConflictStatus::Clear,
        ]);

        app(LeadManager::class)->activate($lead, $organization, $venue, $owner, $admin);
        $lead->refresh();
        $this->assertSame(SalesLeadStatus::Activated, $lead->status);
        $this->assertSame($venue->getKey(), $lead->venue_id);
        $this->assertFalse(Membership::query()->where('user_id', $rep->getKey())->exists());
        $this->actingAs($rep)->get(route('owner.venues.index'))->assertForbidden();

        [$otherOwner, $otherOrganization] = $this->ownedVenue();
        $otherLead = SalesLead::factory()->for($partner, 'partner')->create(['status' => SalesLeadStatus::Onboarding]);
        $this->expectException(ValidationException::class);
        app(LeadManager::class)->activate($otherLead, $organization, $venue, $otherOwner, $admin);
    }

    public function test_partner_can_view_only_own_lead_and_disputed_lead_requires_admin_override(): void
    {
        [$repA, $partnerA] = $this->partner();
        [$repB, $partnerB] = $this->partner();
        $admin = User::factory()->platformAdmin()->create();
        $lead = SalesLead::factory()->for($partnerA, 'partner')->create();
        $disputed = SalesLead::factory()->for($partnerA, 'partner')->create([
            'conflict_status' => LeadConflictStatus::Disputed,
            'protection_started_at' => null,
            'protection_expires_at' => null,
        ]);

        $this->actingAs($repB)->get(route('partner.leads.show', $lead))->assertNotFound();
        $this->actingAs($repA)->patch(route('partner.leads.transition', $disputed), ['status' => 'contacted'])
            ->assertSessionHasErrors('status');

        app(LeadManager::class)->overrideProtection($disputed, $partnerB, $admin, 'Original record was entered by the wrong regional rep.');
        $this->assertSame($partnerB->getKey(), $disputed->refresh()->sales_partner_profile_id);
        $this->assertSame(LeadConflictStatus::Resolved, $disputed->conflict_status);
        $this->assertDatabaseHas('partner_audit_events', ['action' => 'lead.protection_overridden']);
    }

    public function test_verified_won_lead_creates_idempotent_configured_activation_commission(): void
    {
        [, $partner] = $this->partner();
        $admin = User::factory()->platformAdmin()->create();
        CommissionRule::factory()->create([
            'created_by_user_id' => $admin->getKey(),
            'fixed_amount' => '750.00',
        ]);
        [$owner, $organization, $venue] = $this->ownedVenue();
        $lead = SalesLead::factory()->for($partner, 'partner')->create(['status' => SalesLeadStatus::Onboarding]);
        $manager = app(LeadManager::class);
        $manager->activate($lead, $organization, $venue, $owner, $admin);
        $manager->transition($lead->refresh(), SalesLeadStatus::Won, $admin);

        $entry = CommissionEntry::query()->firstOrFail();
        $this->assertSame('750.00', $entry->amount);
        $this->assertSame(CommissionEntryStatus::Pending, $entry->status);
        $this->assertSame('750.00', $entry->rule_snapshot['fixed_amount']);

        app(CommissionLedger::class)->awardActivation($lead->refresh(), $admin);
        $this->assertDatabaseCount('commission_entries', 1);
        CommissionRule::query()->first()->update(['fixed_amount' => '900.00']);
        $this->assertSame('750.00', $entry->refresh()->rule_snapshot['fixed_amount']);
    }

    public function test_admin_adjustments_reversal_and_ledger_immutability_are_auditable(): void
    {
        [, $partner] = $this->partner();
        $admin = User::factory()->platformAdmin()->create();
        $ledger = app(CommissionLedger::class);
        $entry = $ledger->adjust($partner->getKey(), '125.00', 'Approved travel allowance correction.', $admin);
        $ledger->approve($entry, $admin);
        $ledger->reverse($entry->refresh(), 'Allowance entered against wrong period.', $admin);

        $this->assertSame(CommissionEntryStatus::Reversed, $entry->refresh()->status);
        $this->assertDatabaseHas('partner_audit_events', ['action' => 'commission.adjusted']);
        $this->assertDatabaseHas('partner_audit_events', ['action' => 'commission.reversed']);

        $this->expectException(\LogicException::class);
        $entry->refresh()->update(['amount' => '999.00']);
    }

    public function test_manual_payout_lifecycle_reserves_entries_and_paid_reversal_creates_recovery(): void
    {
        [, $partner] = $this->partner();
        $admin = User::factory()->platformAdmin()->create();
        $ledger = app(CommissionLedger::class);
        $entry = $ledger->approve($ledger->adjust($partner->getKey(), '500.00', 'Qualified manual activation correction.', $admin), $admin);
        $service = app(PartnerPayoutService::class);
        $today = CarbonImmutable::today('UTC');
        $payout = $service->create($partner, $today, $today, $admin, 'August manual batch');
        $this->assertSame($payout->getKey(), $entry->refresh()->partner_payout_id);
        $this->assertSame(PartnerPayoutStatus::Pending, $payout->status);

        $service->approve($payout, $admin);
        $service->markPaid($payout->refresh(), $admin, 'GCASH-20260824-001');
        $this->assertSame(PartnerPayoutStatus::Paid, $payout->refresh()->status);
        $this->assertSame(CommissionEntryStatus::Paid, $entry->refresh()->status);

        $ledger->reverse($entry->refresh(), 'External payout later disputed.', $admin);
        $recovery = CommissionEntry::query()->where('reverses_entry_id', $entry->getKey())->firstOrFail();
        $this->assertSame('-500.00', $recovery->amount);
        $this->assertSame(CommissionEntryStatus::Available, $recovery->status);
        $this->assertSame(CommissionEntryStatus::Reversed, $entry->refresh()->status);
    }

    public function test_cancelled_manual_payout_releases_entries_for_future_batch(): void
    {
        [, $partner] = $this->partner();
        $admin = User::factory()->platformAdmin()->create();
        $ledger = app(CommissionLedger::class);
        $entry = $ledger->approve($ledger->adjust($partner->getKey(), '300.00', 'Approved correction.', $admin), $admin);
        $service = app(PartnerPayoutService::class);
        $today = CarbonImmutable::today('UTC');
        $payout = $service->create($partner, $today, $today, $admin);
        $service->approve($payout, $admin);
        $service->cancel($payout->refresh(), $admin, 'Bank reference not available.');

        $this->assertSame(PartnerPayoutStatus::Cancelled, $payout->refresh()->status);
        $this->assertNull($entry->refresh()->partner_payout_id);
        $this->assertSame(CommissionEntryStatus::Available, $entry->status);
    }

    public function test_platform_sales_dashboard_is_admin_only_and_contains_audit_not_tenant_customer_data(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        [$rep, $partner] = $this->partner();
        CommissionEntry::factory()->for($partner, 'partner')->create(['amount' => '42.00']);

        $this->actingAs($rep)->get(route('platform.sales.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('platform.sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Sales/Index')
                ->has('partners')
                ->has('leads')
                ->has('commissions')
                ->has('payouts')
                ->has('audit'));
    }

    /** @return array{User, SalesPartnerProfile} */
    private function partner(SalesPartnerStatus $status = SalesPartnerStatus::Active): array
    {
        $user = User::factory()->create();
        $partner = SalesPartnerProfile::factory()->for($user)->create([
            'status' => $status,
            'suspended_at' => $status === SalesPartnerStatus::Suspended ? now('UTC') : null,
        ]);

        return [$user, $partner];
    }

    /** @return array<string, string> */
    private function leadPayload(): array
    {
        return [
            'business_name' => 'Davao Pickleball Center',
            'contact_person' => 'Mila Owner',
            'contact_method' => 'email',
            'contact_value' => 'mila@example.com',
            'city' => 'Davao City',
            'lead_source' => 'field_outreach',
            'notes' => 'Requested an onboarding demo.',
        ];
    }

    /** @return array{User, Organization, Venue} */
    private function ownedVenue(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->for($owner)->for($organization)->create(['role' => MembershipRole::Owner]);
        $venue = Venue::factory()->for($organization)->create();

        return [$owner, $organization, $venue];
    }
}
