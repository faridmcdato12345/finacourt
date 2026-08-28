<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PlatformServiceFeeType;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\PlatformServiceFeeRule;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformServiceFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_and_view_booking_service_fee_rule(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get(route('platform.payments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Payments/Index')
                ->where('activeRule', null)
                ->where('provider.key', 'manual'));

        $this->actingAs($admin)->post(route('platform.payments.service-fees.store'), [
            'name' => 'FinACourt booking service fee',
            'fee_type' => PlatformServiceFeeType::Percentage->value,
            'percentage_rate' => '7.50',
            'fixed_amount' => '',
            'minimum_fee_amount' => '10.00',
            'maximum_fee_amount' => '99.00',
            'currency' => 'PHP',
            'is_active' => '1',
            'starts_at' => '',
            'ends_at' => '',
        ])->assertRedirect();

        $rule = PlatformServiceFeeRule::query()->sole();
        $this->assertSame(750, $rule->percentage_basis_points);
        $this->assertSame('10.00', $rule->minimum_fee_amount);
        $this->assertSame('99.00', $rule->maximum_fee_amount);
        $this->assertTrue($rule->is_active);
        $this->assertSame($admin->getKey(), $rule->created_by_user_id);

        $this->actingAs($admin)->get(route('platform.payments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeRule.summary', '7.50% of court price')
                ->where('rules.0.name', 'FinACourt booking service fee'));

        $this->actingAs(User::factory()->create())
            ->post(route('platform.payments.service-fees.store'), [
                'name' => 'Blocked fee',
                'fee_type' => PlatformServiceFeeType::Fixed->value,
                'fixed_amount' => '50.00',
                'currency' => 'PHP',
                'is_active' => '1',
            ])->assertForbidden();
    }

    public function test_player_booking_snapshots_service_fee_and_ignores_tampered_amounts(): void
    {
        $rule = PlatformServiceFeeRule::factory()->create([
            'name' => 'Five percent player fee',
            'fee_type' => PlatformServiceFeeType::Percentage,
            'percentage_basis_points' => 500,
        ]);
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource),
            'platform_service_fee_amount' => '0.00',
            'player_total_amount' => '1.00',
            'amount' => '1.00',
        ])->assertRedirect();

        $booking = Booking::query()->with('payment')->sole();

        $this->assertSame('650.00', $booking->total_amount);
        $this->assertSame($rule->getKey(), $booking->platform_service_fee_rule_id);
        $this->assertSame('Five percent player fee', $booking->platform_service_fee_name);
        $this->assertSame(500, $booking->platform_service_fee_rate_basis_points);
        $this->assertSame('32.50', $booking->platform_service_fee_amount);
        $this->assertSame('682.50', $booking->player_total_amount);
        $this->assertSame('682.50', $booking->payment->amount);
        $this->assertSame('650.00', $booking->payment->venue_amount);
        $this->assertSame('32.50', $booking->payment->platform_service_fee_amount);
    }

    public function test_service_fee_snapshot_does_not_change_when_rule_changes(): void
    {
        PlatformServiceFeeRule::factory()->fixed('25.00')->create(['name' => 'Launch fee']);
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)
            ->post(route('player.bookings.store', $venue->slug), $this->holdData($resource, '09:00'))
            ->assertRedirect();
        $first = Booking::query()->sole();

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.payments.service-fees.store'), [
                'name' => 'Newer fee',
                'fee_type' => PlatformServiceFeeType::Fixed->value,
                'percentage_rate' => '',
                'fixed_amount' => '75.00',
                'minimum_fee_amount' => '0.00',
                'maximum_fee_amount' => '',
                'currency' => 'PHP',
                'is_active' => '1',
                'starts_at' => '',
                'ends_at' => '',
            ])->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->post(route('player.bookings.store', $venue->slug), $this->holdData($resource, '10:00'))
            ->assertRedirect();

        $first->refresh();
        $second = Booking::query()->whereKeyNot($first->getKey())->firstOrFail();

        $this->assertSame('25.00', $first->platform_service_fee_amount);
        $this->assertSame('675.00', $first->player_total_amount);
        $this->assertSame('Launch fee', $first->platform_service_fee_name);
        $this->assertSame('75.00', $second->platform_service_fee_amount);
        $this->assertSame('725.00', $second->player_total_amount);
        $this->assertSame('Newer fee', $second->platform_service_fee_name);
    }

    public function test_paymongo_checkout_uses_player_total_and_separate_fee_line_item(): void
    {
        $this->enablePayMongo();
        PlatformServiceFeeRule::factory()->fixed('25.00')->create();
        Http::fake([
            'https://api.paymongo.test/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_service_fee',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.test/cs_test_service_fee',
                    ],
                ],
            ]),
        ]);

        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();

        $this->actingAs($player)
            ->post(route('player.bookings.store', $venue->slug), $this->holdData($resource))
            ->assertRedirect();
        $booking = Booking::query()->with('payment')->sole();

        $this->actingAs($player)
            ->post(route('player.bookings.checkout', $booking->reference), ['amount' => '1.00'])
            ->assertRedirect('https://checkout.paymongo.test/cs_test_service_fee');

        $this->assertSame('675.00', $booking->payment->refresh()->amount);

        Http::assertSent(function ($request) use ($booking): bool {
            $body = $request->data();

            return data_get($body, 'data.attributes.line_items.0.amount') === 65000
                && data_get($body, 'data.attributes.line_items.0.name') === 'Payment Test Courts · Court One'
                && data_get($body, 'data.attributes.line_items.1.amount') === 2500
                && data_get($body, 'data.attributes.line_items.1.name') === 'FinACourt service fee'
                && data_get($body, 'data.attributes.metadata.expected_amount_centavos') === '67500'
                && data_get($body, 'data.attributes.metadata.venue_amount_centavos') === '65000'
                && data_get($body, 'data.attributes.metadata.platform_service_fee_centavos') === '2500'
                && data_get($body, 'data.attributes.metadata.player_total_centavos') === '67500'
                && data_get($body, 'data.attributes.reference_number') === $booking->payment->reference;
        });
    }

    public function test_owner_entered_manual_booking_is_not_silently_charged_player_service_fee(): void
    {
        PlatformServiceFeeRule::factory()->fixed('25.00')->create();
        [$organization, , $resource, $owner] = $this->setupInventory();

        $this->actingAs($owner)->post(route('owner.bookings.store'), [
            'resource_id' => $resource->getKey(),
            'booking_date' => now($organization->timezone)->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Confirmed->value,
            'source' => BookingSource::Manual->value,
            'customer_name' => 'Walk-in Player',
        ])->assertRedirect();

        $booking = Booking::query()->sole();

        $this->assertSame('650.00', $booking->total_amount);
        $this->assertSame('0.00', $booking->platform_service_fee_amount);
        $this->assertSame('650.00', $booking->player_total_amount);
        $this->assertNull($booking->payment_mode);
        $this->assertDatabaseCount('payments', 0);
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
            'name' => 'Court One',
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
            'is_active' => true,
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

    /** @return array<string, mixed> */
    private function holdData(CourtResource $resource, string $start = '09:00'): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addDays(7)->toDateString(),
            'start_time' => $start,
            'duration_minutes' => 60,
            'customer_name' => 'Pat Player',
            'terms' => '1',
        ];
    }

    private function enablePayMongo(): void
    {
        config()->set('payments.default', 'paymongo');
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
}
