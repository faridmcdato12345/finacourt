<?php

namespace App\InteractiveDemo;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\InteractiveDemoWorkspace;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InteractiveDemoWorkspaceManager
{
    private const SESSION_KEY = 'interactive_demo.workspace_id';

    public function enabled(): bool
    {
        return (bool) config('interactive-demo.enabled');
    }

    public function current(Request $request): ?InteractiveDemoWorkspace
    {
        if (! $this->enabled() || ! $request->session()->has(self::SESSION_KEY)) {
            return null;
        }

        $workspace = InteractiveDemoWorkspace::query()
            ->with(['organization', 'venue', 'owner', 'player'])
            ->find($request->session()->get(self::SESSION_KEY));

        if ($workspace === null || ! $workspace->isActive()) {
            $request->session()->forget([self::SESSION_KEY, 'interactive_demo.role']);

            return null;
        }

        $userId = $request->user()?->getKey();
        if ($userId !== null && ! in_array($userId, [$workspace->owner_user_id, $workspace->player_user_id], true)) {
            return null;
        }

        $workspace->forceFill(['last_active_at' => now()])->saveQuietly();

        return $workspace;
    }

    public function create(Request $request): InteractiveDemoWorkspace
    {
        abort_unless($this->enabled(), 404);

        return DB::transaction(function () use ($request): InteractiveDemoWorkspace {
            $suffix = Str::lower(Str::random(8));
            $organization = Organization::query()->create([
                'name' => 'Harbor Sports Demo',
                'slug' => 'interactive-demo-'.$suffix,
                'timezone' => 'Asia/Manila',
                'requires_venue_claim_approval' => false,
            ]);
            $owner = User::query()->create([
                'name' => 'Demo Court Owner',
                'email' => "demo-owner-{$suffix}@example.test",
                'email_verified_at' => now(),
                'password' => Str::random(40),
                'is_platform_admin' => false,
            ]);
            $player = User::query()->create([
                'name' => 'Demo Player',
                'email' => "demo-player-{$suffix}@example.test",
                'email_verified_at' => now(),
                'password' => Str::random(40),
                'is_platform_admin' => false,
            ]);
            Membership::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $owner->getKey(),
                'role' => MembershipRole::Owner,
                'joined_at' => now(),
            ]);
            $venue = Venue::query()->create([
                'organization_id' => $organization->getKey(),
                'name' => 'Harbor Sports Demo',
                'slug' => 'harbor-sports-demo-'.$suffix,
                'description' => 'A private FinACourt demo venue. Explore player booking and owner operations with safe simulated data.',
                'address' => '100 Demo Court Lane',
                'city' => 'Makati City',
                'city_slug' => 'makati-city',
                'province' => 'Metro Manila',
                'province_slug' => 'metro-manila',
                'latitude' => '14.5547000',
                'longitude' => '121.0244000',
                'phone' => '0917 000 0000',
                'email' => 'demo@example.test',
                'is_published' => true,
                'claimed_at' => now(),
                'verified_at' => now(),
                'loyalty_active' => true,
                'loyalty_terms_version' => 1,
                'loyalty_stamps_required' => 5,
                'loyalty_discount_percent' => '10.00',
                'loyalty_discount_cap' => '150.00',
            ]);
            $sport = Sport::query()->firstOrCreate(['slug' => 'badminton'], ['name' => 'Badminton', 'is_active' => true]);
            $venue->sports()->syncWithoutDetaching([$sport->getKey()]);
            foreach (range(0, 6) as $day) {
                OperatingHour::query()->create([
                    'venue_id' => $venue->getKey(),
                    'day_of_week' => $day,
                    'is_closed' => false,
                    'opens_at' => '08:00:00',
                    'closes_at' => '22:00:00',
                ]);
            }
            $courtOne = CourtResource::query()->create([
                'venue_id' => $venue->getKey(),
                'sport_id' => $sport->getKey(),
                'name' => 'Badminton Court 1',
                'resource_type' => ResourceType::Court,
                'setting' => ResourceSetting::Indoor,
                'is_active' => true,
                'base_hourly_rate' => '600.00',
                'currency' => 'PHP',
                'booking_increment_minutes' => 60,
            ]);
            CourtResource::query()->create([
                'venue_id' => $venue->getKey(),
                'sport_id' => $sport->getKey(),
                'name' => 'Badminton Court 2',
                'resource_type' => ResourceType::Court,
                'setting' => ResourceSetting::Covered,
                'is_active' => true,
                'base_hourly_rate' => '700.00',
                'currency' => 'PHP',
                'booking_increment_minutes' => 60,
            ]);
            Promotion::query()->create([
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'resource_id' => $courtOne->getKey(),
                'campaign_token' => 'DEMO-'.Str::upper($suffix),
                'title' => 'Demo weekday 15% off',
                'description' => 'A safe sample promotion for this interactive demo.',
                'promotion_type' => PromotionType::Resource,
                'goal' => PromotionGoal::FillEmptySlots,
                'status' => PromotionStatus::Active,
                'discount_type' => PromotionDiscountType::Percentage,
                'discount_value' => '15.00',
                'starts_on' => now('Asia/Manila')->subDay()->toDateString(),
                'ends_on' => now('Asia/Manila')->addMonth()->toDateString(),
                'targets_specific_slots' => false,
                'is_active' => true,
                'is_public' => true,
            ]);
            $this->seedCompletedBooking($organization, $venue, $courtOne, $owner, $player);
            $workspace = InteractiveDemoWorkspace::query()->create([
                'session_token' => Str::random(48),
                'organization_id' => $organization->getKey(),
                'owner_user_id' => $owner->getKey(),
                'player_user_id' => $player->getKey(),
                'venue_id' => $venue->getKey(),
                'last_active_at' => now(),
                'expires_at' => now()->addHours((int) config('interactive-demo.workspace_lifetime_hours', 12)),
            ]);
            $request->session()->put(self::SESSION_KEY, $workspace->getKey());

            return $workspace->load(['organization', 'venue', 'owner', 'player']);
        });
    }

    public function activate(Request $request, InteractiveDemoWorkspace $workspace, string $role): void
    {
        abort_unless(in_array($role, ['player', 'owner'], true), 404);
        abort_unless($workspace->isActive(), 404);

        $user = $role === 'owner' ? $workspace->owner : $workspace->player;
        Auth::login($user);
        $request->session()->put(self::SESSION_KEY, $workspace->getKey());
        $request->session()->put('interactive_demo.role', $role);

        if ($role === 'owner') {
            $request->session()->put('tenant.organization_id', $workspace->organization_id);
        } else {
            $request->session()->forget('tenant.organization_id');
        }
    }

    public function playerWorkspace(Request $request): ?InteractiveDemoWorkspace
    {
        $workspace = $this->current($request);

        return $workspace !== null && $workspace->player_user_id === $request->user()?->getKey()
            ? $workspace
            : null;
    }

    /**
     * Returns the active player demo workspace, or aborts when a demo player
     * tries to access any venue other than the one provisioned for their demo.
     */
    public function playerWorkspaceForVenue(Request $request, string $venueSlug): ?InteractiveDemoWorkspace
    {
        $workspace = $this->playerWorkspace($request);

        if ($workspace !== null && ! hash_equals($workspace->venue->slug, $venueSlug)) {
            abort(404);
        }

        return $workspace;
    }

    public function isActiveForBooking(Request $request, Booking $booking): bool
    {
        $workspace = $this->playerWorkspace($request);

        return $workspace !== null
            && $workspace->organization_id === $booking->organization_id
            && $workspace->venue_id === $booking->venue_id;
    }

    /** Mark a demo hold as paid without contacting a payment provider or dispatching notifications. */
    public function completeSimulatedCheckout(Request $request, Booking $booking): Booking
    {
        abort_unless($this->isActiveForBooking($request, $booking), 404);

        return DB::transaction(function () use ($booking, $request): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $payment = Payment::query()->where('booking_id', $booking->getKey())->lockForUpdate()->latest('id')->firstOrFail();

            if ($booking->effectiveStatus() !== BookingStatus::Hold || $payment->status !== PaymentStatus::Pending) {
                return $booking;
            }

            $now = now();
            $bookings = $payment->reservation_group_id === null
                ? collect([$booking])
                : Booking::query()->where('reservation_group_id', $payment->reservation_group_id)->lockForUpdate()->get();
            foreach ($bookings as $groupedBooking) {
                $groupedBooking->update([
                    'status' => BookingStatus::Confirmed,
                    'expires_at' => null,
                    'payment_status' => PaymentStatus::Paid,
                ]);
            }
            if ($payment->reservation_group_id !== null) {
                $payment->reservationGroup()->update([
                    'status' => BookingStatus::Confirmed->value,
                    'expires_at' => null,
                ]);
            }
            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $now,
                'provider_reference' => $payment->provider_reference ?? 'demo-checkout-'.$payment->reference,
                'provider_payment_reference' => $payment->provider_payment_reference ?? 'demo-payment-'.$payment->reference,
            ]);
            $payment->transitions()->create([
                'from_status' => PaymentStatus::Pending,
                'to_status' => PaymentStatus::Paid,
                'source' => 'interactive_demo',
                'actor_user_id' => $request->user()->getKey(),
                'external_event_id' => 'interactive-demo:'.$payment->reference,
                'note' => 'Simulated checkout completed. No payment provider, email, or notification was contacted.',
            ]);

            return $booking->refresh();
        });
    }

    private function seedCompletedBooking(Organization $organization, Venue $venue, CourtResource $court, User $owner, User $player): void
    {
        $start = now('Asia/Manila')->subDays(7)->setTime(18, 0)->utc();
        $booking = Booking::query()->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'resource_id' => $court->getKey(),
            'player_user_id' => $player->getKey(),
            'reference' => 'BK-DEMO-'.Str::upper(Str::random(12)),
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Marketplace,
            'customer_name' => $player->name,
            'customer_email' => $player->email,
            'start_at' => $start,
            'end_at' => $start->addHour(),
            'timezone' => $organization->timezone,
            'unit_price' => '600.00',
            'original_unit_price' => '600.00',
            'total_amount' => '600.00',
            'original_total_amount' => '600.00',
            'discount_amount' => '0.00',
            'platform_service_fee_amount' => '0.00',
            'player_total_amount' => '600.00',
            'currency' => 'PHP',
            'payment_mode' => PaymentMode::HostedCheckout,
            'payment_status' => PaymentStatus::Paid,
            'loyalty_eligible' => true,
            'loyalty_terms_version' => 1,
            'loyalty_stamps_required' => 5,
            'loyalty_discount_percent' => '10.00',
            'loyalty_discount_cap' => '150.00',
            'created_by_user_id' => $player->getKey(),
        ]);
        Payment::query()->create([
            'organization_id' => $organization->getKey(),
            'booking_id' => $booking->getKey(),
            'reference' => 'PAY-DEMO-'.Str::upper(Str::random(12)),
            'provider' => 'demo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Paid,
            'amount' => '600.00',
            'venue_amount' => '600.00',
            'platform_service_fee_amount' => '0.00',
            'refunded_amount' => '0.00',
            'currency' => 'PHP',
            // Payment provider references are globally unique. Keep this demo-only
            // reference tied to the already unique booking reference so every
            // browser workspace can be created independently.
            'provider_reference' => 'demo-initial-'.$booking->reference,
            'paid_at' => now(),
            'created_by_user_id' => $player->getKey(),
            'verified_by_user_id' => $owner->getKey(),
        ]);
    }
}
