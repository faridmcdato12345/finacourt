<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Enums\VenueApplicationStatus;
use App\Models\Amenity;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueApplication;
use App\Notifications\OwnerVenueApplicationReviewedNotification;
use App\Notifications\OwnerVenuePublishedNotification;
use App\Notifications\PlatformClaimedVenueReviewRequestedNotification;
use App\Notifications\PlatformVenueApplicationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VenueApplicationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_invited_owner_application_is_private_notifies_platform_and_locks_setup(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerWithOrganization();
        $administrator = User::factory()->create(['is_platform_admin' => true]);
        $sport = Sport::factory()->create();

        $this->actingAs($owner)
            ->get(route('owner.venues.create'))
            ->assertRedirect(route('owner.onboarding.venue'));
        $this->post(route('owner.venues.store'), $this->venueData($sport))
            ->assertRedirect(route('owner.onboarding.venue'));
        $this->assertDatabaseCount('venues', 0);

        $this->actingAs($owner)
            ->post(route('owner.venues.store'), [
                ...$this->venueData($sport),
                'is_published' => true,
                'onboarding' => true,
            ])
            ->assertRedirect(route('owner.onboarding.venue'));

        $venue = Venue::query()->sole();
        $application = VenueApplication::query()->sole();

        $this->assertFalse($venue->is_published);
        $this->assertSame(VenueApplicationStatus::Pending, $application->status);
        $this->assertSame($owner->getKey(), $application->submitted_by_user_id);
        $this->assertTrue($organization->fresh()->requires_venue_claim_approval);
        $this->assertFalse(Venue::query()->marketplace()->whereKey($venue)->exists());
        Notification::assertSentToTimes($administrator, PlatformVenueApplicationSubmittedNotification::class, 1);

        $this->actingAs($administrator)
            ->get(route('platform.venue-applications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/VenueApplications/Index')
                ->where('ownershipReviews.0.id', $application->getKey())
                ->where('ownershipReviews.0.status', 'pending'));

        $this->actingAs($owner);
        $this->get(route('owner.dashboard'))
            ->assertRedirect(route('owner.onboarding.venue'));
        $this->get(route('owner.venues.resources.create', $venue))
            ->assertRedirect(route('owner.onboarding.venue'));
        $this->get(route('owner.onboarding.venue'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/VenueOnboarding/Show')
                ->where('onboarding.source', 'self_service')
                ->where('onboarding.stage', 'ownership_review')
                ->where('onboarding.application.status', 'pending'));
    }

    public function test_platform_ownership_approval_unlocks_setup_but_final_review_controls_discovery(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerWithOrganization();
        $administrator = User::factory()->create(['is_platform_admin' => true]);
        $sport = Sport::factory()->create();
        $venue = $this->submitApplication($owner, $sport);
        $application = $venue->application()->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('platform.venue-applications.approve', $application), [
                'review_notes' => 'Confirmed through the public venue phone and official business page.',
            ])
            ->assertRedirect();

        $this->assertSame(VenueApplicationStatus::Approved, $application->fresh()->status);
        $this->assertFalse($organization->fresh()->requires_venue_claim_approval);
        Notification::assertSentToTimes($owner, OwnerVenueApplicationReviewedNotification::class, 1);

        CourtResource::factory()->for($venue)->for($sport)->create([
            'resource_type' => ResourceType::Court,
            'setting' => ResourceSetting::Outdoor,
            'is_active' => true,
            'base_hourly_rate' => '500.00',
        ]);

        $this->actingAs($owner)
            ->put(route('owner.venues.update', $venue), [
                ...$this->venueData($sport),
                'slug' => $venue->slug,
                'is_published' => true,
                'onboarding' => true,
            ])
            ->assertRedirect(route('owner.onboarding.venue'));

        $venue->refresh();
        $this->assertTrue($venue->is_published);
        $this->assertNotNull($venue->marketplace_review_requested_at);
        $this->assertNull($venue->verified_at);
        $this->assertFalse(Venue::query()->marketplace()->whereKey($venue)->exists());
        Notification::assertSentToTimes($administrator, PlatformClaimedVenueReviewRequestedNotification::class, 1);

        $this->actingAs($administrator)
            ->post(route('platform.venue-applications.verify-marketplace', $application), [
                'review_notes' => 'Checked public details, active court, normal pricing, hours, and photos.',
            ])
            ->assertRedirect();

        $this->assertNotNull($venue->fresh()->verified_at);
        $this->assertTrue(Venue::query()->marketplace()->whereKey($venue)->exists());
        Notification::assertSentToTimes($owner, OwnerVenuePublishedNotification::class, 1);
    }

    public function test_rejected_application_shows_reason_and_owner_can_correct_and_resubmit(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerWithOrganization();
        $administrator = User::factory()->create(['is_platform_admin' => true]);
        $sport = Sport::factory()->create();
        $venue = $this->submitApplication($owner, $sport);
        $application = $venue->application()->firstOrFail();
        $reason = 'The submitted street address does not match the independently sourced venue record.';

        $this->actingAs($administrator)
            ->post(route('platform.venue-applications.reject', $application), [
                'review_notes' => $reason,
            ])
            ->assertRedirect();

        $this->assertSame(VenueApplicationStatus::Rejected, $application->fresh()->status);
        $this->assertTrue($organization->fresh()->requires_venue_claim_approval);
        Notification::assertSentToTimes($owner, OwnerVenueApplicationReviewedNotification::class, 1);

        $this->actingAs($owner)
            ->get(route('owner.onboarding.venue'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('onboarding.stage', 'application_attention')
                ->where('onboarding.application.review_notes', $reason));

        $this->get(route('owner.venues.edit', ['venue' => $venue, 'onboarding' => 1]))
            ->assertOk();
        $this->put(route('owner.venues.update', $venue), [
            ...$this->venueData($sport),
            'slug' => $venue->slug,
            'address' => '200 Corrected Street',
            'onboarding' => true,
        ])->assertRedirect(route('owner.onboarding.venue'));

        $application->refresh();
        $this->assertSame(VenueApplicationStatus::Pending, $application->status);
        $this->assertNull($application->review_notes);
        $this->assertTrue($organization->fresh()->requires_venue_claim_approval);
        Notification::assertSentToTimes($administrator, PlatformVenueApplicationSubmittedNotification::class, 2);
    }

    public function test_only_platform_administrators_can_review_venue_applications(): void
    {
        [$owner] = $this->ownerWithOrganization();
        $sport = Sport::factory()->create();
        $application = $this->submitApplication($owner, $sport)->application()->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->post(route('platform.venue-applications.approve', $application), [
                'review_notes' => 'This ordinary user must not be able to approve an ownership application.',
            ])
            ->assertForbidden();

        $this->assertSame(VenueApplicationStatus::Pending, $application->fresh()->status);
    }

    /** @return array{User, Organization} */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'requires_venue_claim_approval' => true,
        ]);
        Membership::factory()->for($owner)->for($organization)->create([
            'role' => MembershipRole::Owner,
        ]);

        return [$owner, $organization];
    }

    private function submitApplication(User $owner, Sport $sport): Venue
    {
        $this->actingAs($owner)
            ->post(route('owner.venues.store'), [
                ...$this->venueData($sport),
                'onboarding' => true,
            ])
            ->assertRedirect(route('owner.onboarding.venue'));

        return Venue::query()->sole();
    }

    /** @return array<string, mixed> */
    private function venueData(Sport $sport): array
    {
        return [
            'name' => 'Northside Pickleball Club',
            'slug' => null,
            'description' => 'A newly submitted sports venue.',
            'address' => '100 Main Street',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'latitude' => '14.5547000',
            'longitude' => '121.0244000',
            'phone' => '+63 2 8000 0000',
            'email' => 'venue@example.com',
            'website' => 'https://example.com',
            'is_published' => false,
            'sports' => [$sport->getKey()],
            'amenities' => Amenity::factory()->count(2)->create()->modelKeys(),
        ];
    }
}
