<?php

namespace Tests\Feature;

use App\Directory\VenueClaimInvitationService;
use App\Directory\VenueDirectoryManager;
use App\Enums\AnalyticsEventType;
use App\Enums\DirectoryClaimStatus;
use App\Enums\DirectoryListingStatus;
use App\Enums\MembershipRole;
use App\Enums\VenueClaimProofMethod;
use App\Enums\VenueClaimProofStatus;
use App\Models\AnalyticsEvent;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PsgcLocation;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueClaimInvitation;
use App\Models\VenueClaimRequest;
use App\Models\VenueDirectoryListing;
use App\Notifications\VenueClaimVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnclaimedVenueDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_creates_a_provenance_controlled_draft_without_owner_credentials(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $sport = Sport::factory()->create();
        $usersBefore = User::query()->count();

        $this->actingAs($admin)
            ->post(route('platform.directory.store'), $this->listingPayload($sport))
            ->assertRedirect();

        $listing = VenueDirectoryListing::query()->sole();
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, Membership::query()->count());
        $this->assertSame(0, Venue::query()->count());
        $this->assertNull($listing->claimed_venue_id);
        $this->assertSame(DirectoryListingStatus::Draft, $listing->status);
        $this->assertSame($admin->getKey(), $listing->created_by_user_id);
        $this->assertSame($admin->getKey(), $listing->rights_confirmed_by_user_id);
        $this->assertTrue($listing->sports()->whereKey($sport)->exists());
        $this->assertCount(7, $listing->hours);
        $this->assertDatabaseHas('venue_directory_audits', [
            'venue_directory_listing_id' => $listing->getKey(),
            'event_type' => 'listing_created',
        ]);
    }

    public function test_only_platform_admin_can_manage_directory_provenance(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $listing = VenueDirectoryListing::factory()->published()->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('platform.directory.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->get(route('platform.directory.edit', $listing))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Directory/Edit')
                ->where('listing.slug', $listing->slug));
    }

    public function test_only_platform_admin_can_create_a_hashed_private_owner_link(): void
    {
        $listing = VenueDirectoryListing::factory()->published()->create();
        [$owner, $organization] = $this->ownerWithOrganization();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('platform.directory.claim-invitations.store', $listing))
            ->assertForbidden();

        $admin = User::factory()->platformAdmin()->create();
        $response = $this->actingAs($admin)
            ->post(route('platform.directory.claim-invitations.store', $listing))
            ->assertOk()
            ->assertSessionMissing('claim_invitation')
            ->assertJsonStructure(['status', 'invitation' => ['id', 'expires_at'], 'claim_invitation' => ['url', 'expires_at']]);

        $invitation = VenueClaimInvitation::query()->sole();
        $url = $response->json('claim_invitation.url');
        $token = basename((string) parse_url($url, PHP_URL_PATH));

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame(VenueClaimInvitation::hashToken($token), $invitation->getRawOriginal('token_hash'));
        $this->assertNotSame($token, $invitation->getRawOriginal('token_hash'));
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertDatabaseHas('venue_directory_audits', [
            'venue_directory_listing_id' => $listing->getKey(),
            'event_type' => 'claim_invitation_created',
        ]);
    }

    public function test_replacing_expiring_and_revoking_private_links_blocks_old_secrets(): void
    {
        config(['directory.claim_invitation_hours' => 1]);
        $listing = VenueDirectoryListing::factory()->published()->create();
        $admin = User::factory()->platformAdmin()->create();
        [$owner, $organization] = $this->ownerWithOrganization();
        $service = app(VenueClaimInvitationService::class);

        $expiredToken = $service->issue($listing, $admin)['token'];
        $this->travel(2)->hours();
        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.directory-claims.invitations.create', $expiredToken))
            ->assertNotFound();

        $replacedToken = $service->issue($listing, $admin)['token'];
        $current = $service->issue($listing, $admin);

        $this->get(route('owner.directory-claims.invitations.create', $replacedToken))
            ->assertNotFound();
        $this->get(route('owner.directory-claims.invitations.create', $current['token']))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('platform.directory.claim-invitations.destroy', [$listing, $current['invitation']]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.directory-claims.invitations.create', $current['token']))
            ->assertNotFound();
    }

    public function test_hiding_a_listing_revokes_its_private_owner_link(): void
    {
        $listing = VenueDirectoryListing::factory()->published()->create();
        $admin = User::factory()->platformAdmin()->create();
        [$owner, $organization] = $this->ownerWithOrganization();
        $issued = app(VenueClaimInvitationService::class)->issue($listing, $admin);

        app(VenueDirectoryManager::class)->markClosed(
            $listing,
            $admin,
            'The venue asked us to hide this directory record while details are checked.',
        );

        $this->assertNotNull($issued['invitation']->fresh()->revoked_at);
        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.directory-claims.invitations.create', $issued['token']))
            ->assertNotFound();
    }

    public function test_new_owner_registration_returns_to_the_private_venue_link(): void
    {
        $listing = VenueDirectoryListing::factory()->published()->create();
        $token = $this->claimInvitationToken($listing);
        $invitationUrl = route('owner.directory-claims.invitations.create', $token);

        $this->get($invitationUrl)->assertRedirect(route('login'));
        $this->post(route('register'), [
            'name' => 'Invited Owner',
            'email' => 'invited-owner@example.com',
            'organization_name' => 'Invited Owner Courts',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect($invitationUrl);
    }

    public function test_admin_verification_publication_and_edits_follow_the_audited_state_machine(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $sport = Sport::factory()->create();
        $this->actingAs($admin)->post(route('platform.directory.store'), $this->listingPayload($sport));
        $listing = VenueDirectoryListing::query()->sole();

        $this->post(route('platform.directory.verify', $listing), [
            'verification_notes' => 'Name, address, contact, sport, and hours checked against the official source.',
        ])->assertRedirect();
        $this->post(route('platform.directory.publish', $listing))->assertRedirect();
        $this->assertSame(DirectoryListingStatus::Published, $listing->fresh()->status);
        $this->assertNotNull($listing->fresh()->last_verified_at);

        $updated = $this->listingPayload($sport);
        $updated['name'] = 'Corrected Lawfully Sourced Sports Venue';
        $updated['source_url'] = 'https://venue.example.com/current-details';
        $this->put(route('platform.directory.update', $listing), $updated)->assertRedirect();

        $listing->refresh();
        $this->assertSame('Corrected Lawfully Sourced Sports Venue', $listing->name);
        $this->assertSame(DirectoryListingStatus::Draft, $listing->status);
        $this->assertNull($listing->last_verified_at);
        $this->assertDatabaseHas('venue_directory_audits', [
            'venue_directory_listing_id' => $listing->getKey(),
            'event_type' => 'information_verified',
        ]);
        $this->assertDatabaseHas('venue_directory_audits', [
            'venue_directory_listing_id' => $listing->getKey(),
            'event_type' => 'listing_updated',
        ]);
    }

    public function test_public_directory_exposes_only_verified_directory_facts_and_no_transactional_claims(): void
    {
        $sport = Sport::factory()->create(['name' => 'Pickleball', 'slug' => 'pickleball']);
        $listing = $this->publishedListing($sport, [
            'name' => 'Legitimate Public Courts',
            'description' => 'An original factual summary.',
            'source_reference' => 'Private registry notes must remain private.',
            'verification_notes' => 'Private administrator verification notes.',
        ]);
        $draft = VenueDirectoryListing::factory()->create(['name' => 'Private Draft']);

        $this->get(route('marketplace.directory.index'))
            ->assertOk()
            ->assertSee('Legitimate Public Courts')
            ->assertDontSee('Private Draft');

        $this->get(route('marketplace.directory.show', $listing->slug))
            ->assertOk()
            ->assertSee('Not yet managed on FinACourt')
            ->assertSee('This page uses public information and does not mean the venue is a FinACourt partner.')
            ->assertSee('This venue is not bookable on FinACourt yet')
            ->assertDontSee('Private registry notes must remain private.')
            ->assertDontSee('Private administrator verification notes.')
            ->assertDontSee('Request an ownership review')
            ->assertDontSee('Request ownership review')
            ->assertDontSee('Book now')
            ->assertDontSee('Verified venue');

        $this->get(route('marketplace.directory.show', $draft->slug))->assertNotFound();
        $this->get("/owner/directory/{$listing->slug}/claim")->assertNotFound();

        $event = AnalyticsEvent::query()->where('venue_directory_listing_id', $listing->getKey())->sole();
        $this->assertSame(AnalyticsEventType::VenueProfileView, $event->event_type);
        $this->assertNull($event->organization_id);
        $this->assertNull($event->venue_id);
        $this->assertNotNull($event->visitor_hash);

        $this->get(route('marketplace.sitemap'))
            ->assertOk()
            ->assertSee(route('marketplace.directory.show', $listing->slug), false)
            ->assertDontSee(route('marketplace.directory.show', $draft->slug), false);
    }

    public function test_homepage_surfaces_published_directory_venues_without_mixing_them_into_bookable_results(): void
    {
        $sport = Sport::factory()->create(['name' => 'Pickleball', 'slug' => 'pickleball']);
        $listing = $this->publishedListing($sport, [
            'name' => 'Marawi Pickleball Sports Center',
            'city' => 'Marawi City',
            'city_slug' => 'marawi-city',
            'province' => 'Lanao del Sur',
            'province_slug' => 'lanao-del-sur',
        ]);
        $draft = VenueDirectoryListing::factory()->create(['name' => 'Unreviewed Directory Venue']);
        $draft->sports()->attach($sport);

        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('data-directory-venues', false)
            ->assertSee('More places to play')
            ->assertSee($listing->name)
            ->assertSee(route('marketplace.directory.show', $listing->slug), false)
            ->assertSee('Not yet bookable')
            ->assertSee('Contact the venue directly to confirm availability')
            ->assertDontSee($draft->name);

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertDontSee($listing->name);
    }

    public function test_claim_requires_the_real_tenant_owner_and_ignores_browser_tenant_input(): void
    {
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport);
        $invitationToken = $this->claimInvitationToken($listing);
        [$owner, $organization] = $this->ownerWithOrganization();
        $otherOrganization = Organization::factory()->create();
        $payload = $this->claimPayload() + ['organization_id' => $otherOrganization->getKey()];

        $this->get(route('owner.directory-claims.invitations.create', $invitationToken))
            ->assertRedirect(route('login'));

        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($organization)->create(['role' => MembershipRole::Staff]);
        $this->actingAs($staff)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.directory-claims.invitations.create', $invitationToken))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $invitationToken), $payload)
            ->assertRedirect(route('owner.directory-claims.index'));

        $claim = VenueClaimRequest::query()->sole();
        $this->assertSame($organization->getKey(), $claim->organization_id);
        $this->assertSame($owner->getKey(), $claim->requester_user_id);
        $this->assertSame(DirectoryClaimStatus::Pending, $claim->status);
        $this->assertSame(0, Venue::query()->count());
        $invitation = VenueClaimInvitation::query()->sole();
        $this->assertSame($owner->getKey(), $invitation->used_by_user_id);
        $this->assertSame($claim->getKey(), $invitation->venue_claim_request_id);
        $this->assertNotNull($invitation->used_at);

        $this->post(route('owner.directory-claims.invitations.store', $invitationToken), $this->claimPayload())
            ->assertNotFound();
        $this->assertSame(1, VenueClaimRequest::query()->count());
    }

    public function test_claim_requires_a_verified_account_email(): void
    {
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport);
        $invitationToken = $this->claimInvitationToken($listing);
        $owner = User::factory()->unverified()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.directory-claims.invitations.create', $invitationToken))
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, VenueClaimRequest::query()->count());
    }

    public function test_public_email_challenge_goes_only_to_the_independently_sourced_venue_email(): void
    {
        Notification::fake();
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport, ['email' => 'frontdesk@venue.example']);
        $invitationToken = $this->claimInvitationToken($listing);
        [$owner, $organization] = $this->ownerWithOrganization();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $invitationToken), $this->claimPayload())
            ->assertRedirect(route('owner.directory-claims.index'));

        $claim = VenueClaimRequest::query()->sole();
        $capturedCode = null;
        Notification::assertSentOnDemand(
            VenueClaimVerificationCode::class,
            function (
                VenueClaimVerificationCode $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ) use (&$capturedCode): bool {
                $capturedCode = $notification->code;

                return $channels === ['mail']
                    && $notifiable->routes['mail'] === 'frontdesk@venue.example';
            },
        );

        $this->assertSame(VenueClaimProofMethod::PublicEmailCode, $claim->proof_method);
        $this->assertSame(VenueClaimProofStatus::Pending, $claim->proof_status);
        $this->assertSame('fr•••••••@venue.example', $claim->proof_destination);
        $this->assertNotNull($claim->getRawOriginal('proof_code_hash'));
        $this->assertNotSame($capturedCode, $claim->getRawOriginal('proof_code_hash'));

        $this->post(route('owner.directory-claims.proof.verify', $claim), ['code' => $capturedCode])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame(VenueClaimProofStatus::Verified, $claim->proof_status);
        $this->assertNotNull($claim->proof_verified_at);
        $this->assertNotNull($claim->approval_available_at);
        $this->assertNull($claim->proof_code_hash);
    }

    public function test_email_proof_is_tenant_scoped_and_locks_after_repeated_wrong_codes(): void
    {
        Notification::fake();
        config(['directory.claim_verification_max_attempts' => 3]);
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport, ['email' => 'manager@venue.example']);
        $invitationToken = $this->claimInvitationToken($listing);
        [$owner, $organization] = $this->ownerWithOrganization();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $invitationToken), $this->claimPayload());
        $claim = VenueClaimRequest::query()->sole();

        [$otherOwner, $otherOrganization] = $this->ownerWithOrganization();
        $this->actingAs($otherOwner)
            ->withSession(['tenant.organization_id' => $otherOrganization->getKey()])
            ->post(route('owner.directory-claims.proof.verify', $claim), ['code' => '111111'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()]);
        foreach (range(1, 3) as $_attempt) {
            $this->post(route('owner.directory-claims.proof.verify', $claim), ['code' => '111111'])
                ->assertSessionHasErrors('code');
        }

        $claim->refresh();
        $this->assertSame(3, $claim->proof_attempts);
        $this->assertSame(VenueClaimProofStatus::Locked, $claim->proof_status);
        $this->post(route('owner.directory-claims.proof.email', $claim))
            ->assertSessionHasErrors('proof');
        $this->assertSame(VenueClaimProofStatus::Locked, $claim->fresh()->proof_status);
        $this->assertDatabaseHas('venue_directory_audits', [
            'venue_directory_listing_id' => $listing->getKey(),
            'event_type' => 'claim_email_code_locked',
        ]);
    }

    public function test_platform_cannot_approve_a_claim_without_independent_proof_or_during_the_safety_hold(): void
    {
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport);
        $invitationToken = $this->claimInvitationToken($listing);
        [$owner, $organization] = $this->ownerWithOrganization();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $invitationToken), $this->claimPayload());
        $claim = VenueClaimRequest::query()->sole();

        $this->actingAs($admin)
            ->post(route('platform.directory.claims.approve', $claim), [
                'review_notes' => 'The claimant information alone is not independent proof.',
            ])
            ->assertSessionHasErrors('claim');
        $this->assertSame(0, Venue::query()->count());

        $this->post(route('platform.directory.claims.verify-proof', $claim), [
            'proof_method' => VenueClaimProofMethod::OfficialPhoneCall->value,
            'proof_notes' => 'Called the phone number already published by the venue and confirmed control with the manager.',
        ])->assertRedirect();

        $this->post(route('platform.directory.claims.approve', $claim), [
            'review_notes' => 'Independent contact was confirmed but the safety hold is still active.',
        ])->assertSessionHasErrors('claim');
        $this->assertSame(0, Venue::query()->count());

        $this->travel(((int) config('directory.claim_approval_hold_hours')) + 1)->hours();
        $this->post(route('platform.directory.claims.approve', $claim), [
            'review_notes' => 'The safety hold finished without a dispute and the independent check remains valid.',
        ])->assertRedirect();

        $this->assertSame(1, Venue::query()->count());
    }

    public function test_approved_claim_attaches_an_unpublished_unverified_venue_and_transfers_aggregate_activity(): void
    {
        $this->seedDirectoryLocationHierarchy();
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport, [
            'city' => 'City of Digos',
            'city_slug' => 'city-of-digos',
            'province' => 'Davao del Sur',
            'province_slug' => 'davao-del-sur',
            'psgc_region_code' => '1100000000',
            'psgc_province_code' => '1102400000',
            'psgc_city_municipality_code' => '1102403000',
            'latitude' => '14.5995124',
            'longitude' => '120.9842195',
            'coordinates_verified_at' => now(),
        ]);
        $invitationToken = $this->claimInvitationToken($listing);
        [$owner, $organization] = $this->ownerWithOrganization();
        $admin = User::factory()->platformAdmin()->create();

        $this->get(route('marketplace.directory.show', $listing->slug))->assertOk();
        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $invitationToken), $this->claimPayload());
        $claim = VenueClaimRequest::query()->sole();
        $usersBefore = User::query()->count();

        $this->verifyClaimProofAndFinishSafetyHold($claim, $admin);

        $this->actingAs($admin)
            ->post(route('platform.directory.claims.approve', $claim), [
                'review_notes' => 'Registration and venue control were independently verified.',
            ])
            ->assertRedirect();

        $venue = Venue::query()->sole();
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($organization->getKey(), $venue->organization_id);
        $this->assertFalse($venue->is_published);
        $this->assertNull($venue->verified_at);
        $this->assertNotNull($venue->claimed_at);
        $this->assertSame('directory_listing', $venue->coordinates_source);
        $this->assertSame('1100000000', $venue->psgc_region_code);
        $this->assertSame('1102400000', $venue->psgc_province_code);
        $this->assertSame('1102403000', $venue->psgc_city_municipality_code);
        $this->assertTrue($venue->sports()->whereKey($sport)->exists());
        $this->assertCount(7, $venue->operatingHours);
        $this->assertSame(0, CourtResource::query()->count());

        $claim->refresh();
        $listing->refresh();
        $this->assertSame(DirectoryClaimStatus::Approved, $claim->status);
        $this->assertNull($claim->active_claim_key);
        $this->assertSame($venue->getKey(), $claim->approved_venue_id);
        $this->assertSame(DirectoryListingStatus::Claimed, $listing->status);
        $this->assertSame($venue->getKey(), $listing->claimed_venue_id);

        $event = AnalyticsEvent::query()->where('venue_directory_listing_id', $listing->getKey())->sole();
        $this->assertSame($organization->getKey(), $event->organization_id);
        $this->assertSame($venue->getKey(), $event->venue_id);

        $this->get(route('marketplace.directory.show', $listing->slug))
            ->assertOk()
            ->assertSee('Owner setup in progress')
            ->assertDontSee('Yes, this is my venue');

        [$otherOwner, $otherOrganization] = $this->ownerWithOrganization();
        $this->actingAs($otherOwner)
            ->withSession(['tenant.organization_id' => $otherOrganization->getKey()])
            ->get(route('owner.venues.show', $venue))
            ->assertForbidden();
    }

    public function test_claimed_venue_needs_a_separate_marketplace_review_and_can_be_revoked(): void
    {
        $sport = Sport::factory()->create(['name' => 'Pickleball', 'slug' => 'pickleball']);
        $listing = $this->publishedListing($sport, [
            'name' => 'Claim Review Courts',
            'city' => 'Davao City',
            'city_slug' => 'davao-city',
        ]);
        $invitationToken = $this->claimInvitationToken($listing);
        [$owner, $organization] = $this->ownerWithOrganization();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $invitationToken), $this->claimPayload());
        $claim = VenueClaimRequest::query()->sole();
        $this->verifyClaimProofAndFinishSafetyHold($claim, $admin);
        $this->actingAs($admin)->post(route('platform.directory.claims.approve', $claim), [
            'review_notes' => 'Independent ownership proof and the safety hold were reviewed before account attachment.',
        ]);

        $venue = Venue::query()->sole();
        CourtResource::factory()->for($venue)->for($sport)->create();
        $venue->update(['is_published' => true]);

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertDontSee($venue->name);
        $this->get(route('marketplace.venues.show', $venue->slug))->assertNotFound();

        $this->actingAs($admin)
            ->post(route('platform.directory.claimed-venue.verify', $listing), [
                'verification_notes' => 'The venue setup, active court, public details, and ownership audit were checked before launch.',
            ])->assertRedirect();

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertSee($venue->name);
        $this->get(route('marketplace.venues.show', $venue->slug))->assertOk();

        $this->actingAs($admin)
            ->post(route('platform.directory.claimed-venue.revoke', $listing), [
                'reason' => 'A credible ownership dispute requires immediate removal while the platform investigates.',
            ])->assertRedirect();

        $venue->refresh();
        $this->assertFalse($venue->is_published);
        $this->assertNull($venue->verified_at);
        $this->get(route('marketplace.courts.index'))->assertDontSee($venue->name);
        $this->assertDatabaseHas('venue_directory_audits', [
            'venue_directory_listing_id' => $listing->getKey(),
            'event_type' => 'claimed_venue_marketplace_access_revoked',
        ]);
    }

    public function test_rejected_or_cancelled_claim_releases_the_listing_for_a_new_request(): void
    {
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport);
        $firstInvitationToken = $this->claimInvitationToken($listing);
        [$owner, $organization] = $this->ownerWithOrganization();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $firstInvitationToken), $this->claimPayload());
        $claim = VenueClaimRequest::query()->sole();

        $this->actingAs($admin)
            ->post(route('platform.directory.claims.reject', $claim), [
                'review_notes' => 'The submitted evidence could not be independently verified.',
            ]);
        $this->assertSame(DirectoryClaimStatus::Rejected, $claim->fresh()->status);

        $secondInvitationToken = $this->claimInvitationToken($listing, $admin);
        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->post(route('owner.directory-claims.invitations.store', $secondInvitationToken), $this->claimPayload())
            ->assertRedirect(route('owner.directory-claims.index'));
        $second = VenueClaimRequest::query()->latest('id')->firstOrFail();
        $this->delete(route('owner.directory-claims.cancel', $second))->assertRedirect();
        $this->assertSame(DirectoryClaimStatus::Cancelled, $second->fresh()->status);
    }

    public function test_public_corrections_and_closed_state_are_auditable_and_remove_discovery(): void
    {
        $sport = Sport::factory()->create();
        $listing = $this->publishedListing($sport);
        $admin = User::factory()->platformAdmin()->create();

        $this->post(route('marketplace.directory.report', $listing), [
            'report_type' => 'closed',
            'contact_email' => 'reporter@example.com',
            'details' => 'The venue sign says this location permanently closed last month.',
        ])->assertRedirect();
        $report = $listing->reports()->sole();

        $this->actingAs($admin)
            ->patch(route('platform.directory.reports.review', $report), [
                'status' => 'resolved',
                'review_notes' => 'The closure report was checked against the official source.',
            ])
            ->assertRedirect();
        $this->post(route('platform.directory.close', $listing), [
            'reason' => 'Official venue source confirms this location is permanently closed.',
        ])->assertRedirect();

        $this->get(route('marketplace.directory.index'))->assertDontSee($listing->name);
        $this->get(route('marketplace.directory.show', $listing->slug))
            ->assertOk()
            ->assertSee('This venue may be closed')
            ->assertSee('noindex,follow', false);
        $this->assertDatabaseHas('venue_directory_audits', [
            'venue_directory_listing_id' => $listing->getKey(),
            'event_type' => 'listing_marked_closed',
        ]);
    }

    /** @return array{User, Organization} */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$owner, $organization];
    }

    private function claimInvitationToken(
        VenueDirectoryListing $listing,
        ?User $administrator = null,
    ): string {
        $administrator ??= User::factory()->platformAdmin()->create();

        return app(VenueClaimInvitationService::class)->issue($listing, $administrator)['token'];
    }

    private function publishedListing(Sport $sport, array $attributes = []): VenueDirectoryListing
    {
        $listing = VenueDirectoryListing::factory()->published()->create($attributes);
        $listing->sports()->attach($sport);

        foreach (range(0, 6) as $day) {
            $listing->hours()->create([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        return $listing;
    }

    private function seedDirectoryLocationHierarchy(): void
    {
        PsgcLocation::query()->create([
            'code' => '1100000000',
            'name' => 'Region XI (Davao Region)',
            'level' => 'region',
            'type' => 'Region',
            'source_version' => 'test',
        ]);
        PsgcLocation::query()->create([
            'code' => '1102400000',
            'parent_code' => '1100000000',
            'name' => 'Davao del Sur',
            'level' => 'province',
            'type' => 'Province',
            'source_version' => 'test',
        ]);
        PsgcLocation::query()->create([
            'code' => '1102403000',
            'parent_code' => '1102400000',
            'name' => 'City of Digos',
            'level' => 'city',
            'type' => 'Component City',
            'source_version' => 'test',
        ]);
    }

    private function verifyClaimProofAndFinishSafetyHold(
        VenueClaimRequest $claim,
        User $administrator,
    ): void {
        $this->actingAs($administrator)
            ->post(route('platform.directory.claims.verify-proof', $claim), [
                'proof_method' => VenueClaimProofMethod::OfficialPhoneCall->value,
                'proof_notes' => 'Called the official public venue number and independently confirmed that the claimant controls the business.',
            ])
            ->assertRedirect();

        $this->travel(((int) config('directory.claim_approval_hold_hours')) + 1)->hours();
    }

    /** @return array<string, mixed> */
    private function listingPayload(Sport $sport): array
    {
        return [
            'name' => 'Lawfully Sourced Sports Venue',
            'description' => 'An original factual summary written by the platform administrator.',
            'address' => '100 Public Road',
            'city' => 'Davao City',
            'province' => 'Davao del Sur',
            'country' => 'Philippines',
            'latitude' => '7.0731000',
            'longitude' => '125.6128000',
            'coordinates_verified' => true,
            'phone' => '+63 900 000 0000',
            'email' => 'public@example.com',
            'website' => 'https://venue.example.com',
            'source_type' => 'official_website',
            'source_url' => 'https://venue.example.com/contact',
            'source_reference' => 'Official contact page checked manually',
            'rights_confirmed' => true,
            'sports' => [$sport->getKey()],
            'hours' => collect(range(0, 6))->map(fn (int $day) => [
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ])->all(),
        ];
    }

    /** @return array<string, string> */
    private function claimPayload(): array
    {
        return [
            'relationship_to_venue' => 'owner',
            'verification_contact' => 'owner-business@example.com',
            'evidence_details' => 'I own and operate this venue and can provide business registration and utility documents.',
        ];
    }
}
