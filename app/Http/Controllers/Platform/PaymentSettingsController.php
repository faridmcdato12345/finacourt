<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PlatformServiceFeeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlatformServiceFeeRuleRequest;
use App\Http\Requests\UpdatePlatformServiceFeeRuleRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformServiceFeeRule;
use App\Payments\PaymentProviderRegistry;
use App\Payments\PlatformServiceFeeCalculator;
use App\Payments\Providers\PayMongoPaymentProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingsController extends Controller
{
    public function index(
        PaymentProviderRegistry $providers,
        PlatformServiceFeeCalculator $serviceFees,
    ): Response {
        $provider = $providers->default();
        $activeRule = $serviceFees->activeRule();

        return Inertia::render('Platform/Payments/Index', [
            'activeRule' => $activeRule ? $this->rulePayload($activeRule) : null,
            'rules' => PlatformServiceFeeRule::query()
                ->with('createdBy:id,name,email')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (PlatformServiceFeeRule $rule) => $this->rulePayload($rule)),
            'metrics' => $this->metrics(),
            'provider' => [
                'key' => $provider->key(),
                'mode' => $provider->mode()->value,
                'mode_label' => $provider->mode()->label(),
                'hosted_checkout_available' => $provider->supportsHostedCheckout(),
                'configuration_issues' => $provider instanceof PayMongoPaymentProvider
                    ? $provider->configurationIssues()
                    : [],
                'paymongo_pass_on_fees' => $provider instanceof PayMongoPaymentProvider
                    && (bool) config('payments.providers.paymongo.pass_on_fees', false),
            ],
            'feeTypes' => collect(PlatformServiceFeeType::cases())->map(fn (PlatformServiceFeeType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->all(),
        ]);
    }

    public function store(StorePlatformServiceFeeRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $type = PlatformServiceFeeType::from($validated['fee_type']);
        $isActive = (bool) $validated['is_active'];

        DB::transaction(function () use ($validated, $type, $isActive, $request): void {
            if ($isActive) {
                $this->pauseActiveRules();
            }

            PlatformServiceFeeRule::query()->create([
                'name' => $validated['name'],
                'fee_type' => $type,
                'percentage_basis_points' => $type === PlatformServiceFeeType::Percentage
                    ? $this->basisPoints((string) $validated['percentage_rate'])
                    : null,
                'fixed_amount' => $type === PlatformServiceFeeType::Fixed
                    ? $this->money($validated['fixed_amount'])
                    : null,
                'minimum_fee_amount' => $this->money($validated['minimum_fee_amount'] ?? '0'),
                'maximum_fee_amount' => isset($validated['maximum_fee_amount'])
                    ? $this->money($validated['maximum_fee_amount'])
                    : null,
                'currency' => strtoupper($validated['currency']),
                'is_active' => $isActive,
                'starts_at' => isset($validated['starts_at'])
                    ? CarbonImmutable::parse($validated['starts_at'])->utc()
                    : null,
                'ends_at' => isset($validated['ends_at'])
                    ? CarbonImmutable::parse($validated['ends_at'])->utc()
                    : null,
                'created_by_user_id' => $request->user()->getKey(),
            ]);
        });

        return back()->with('status', $isActive
            ? 'Booking service fee saved and turned on for new player bookings.'
            : 'Booking service fee rule saved as inactive.');
    }

    public function update(
        UpdatePlatformServiceFeeRuleRequest $request,
        PlatformServiceFeeRule $rule,
    ): RedirectResponse {
        $isActive = (bool) $request->validated('is_active');

        DB::transaction(function () use ($rule, $isActive): void {
            if ($isActive) {
                $this->pauseActiveRules($rule->getKey());
            }

            $rule->update([
                'is_active' => $isActive,
                'deactivated_at' => $isActive ? null : now(),
            ]);
        });

        return back()->with('status', $isActive
            ? 'This booking service fee is now active for new player bookings.'
            : 'This booking service fee is paused.');
    }

    /** @return array<string, mixed> */
    private function metrics(): array
    {
        $qualified = $this->qualifiedMarketplaceBookings();
        $feeTotal = (clone $qualified)->sum('platform_service_fee_amount') ?: 0;
        $playerTotal = (clone $qualified)->sum('player_total_amount') ?: 0;
        $venueTotal = (clone $qualified)->sum('total_amount') ?: 0;
        $feeBookingCount = (clone $qualified)
            ->where('platform_service_fee_amount', '>', 0)
            ->count();
        $pendingFees = Booking::query()
            ->where('source', BookingSource::Marketplace)
            ->where('platform_service_fee_amount', '>', 0)
            ->whereIn('status', [BookingStatus::Hold, BookingStatus::Confirmed])
            ->where('payment_status', PaymentStatus::Pending)
            ->sum('platform_service_fee_amount') ?: 0;

        return [
            'qualified_bookings' => (clone $qualified)->count(),
            'bookings_with_fee' => $feeBookingCount,
            'service_fee_total' => $this->money($feeTotal),
            'pending_service_fee_total' => $this->money($pendingFees),
            'venue_price_total' => $this->money($venueTotal),
            'player_total' => $this->money($playerTotal),
            'average_service_fee' => $feeBookingCount === 0
                ? '0.00'
                : $this->money((float) $feeTotal / $feeBookingCount),
            'recent_payments' => Payment::query()
                ->with([
                    'booking:id,reference,venue_id,total_amount,platform_service_fee_amount,player_total_amount',
                    'booking.venue:id,name',
                ])
                ->where('platform_service_fee_amount', '>', 0)
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (Payment $payment) => [
                    'id' => $payment->getKey(),
                    'reference' => $payment->reference,
                    'booking_reference' => $payment->booking?->reference,
                    'venue' => $payment->booking?->venue?->name,
                    'status' => $payment->status->label(),
                    'amount' => $payment->amount,
                    'venue_amount' => $payment->venue_amount,
                    'platform_service_fee_amount' => $payment->platform_service_fee_amount,
                    'currency' => $payment->currency,
                    'created_at' => $payment->created_at?->toDateString(),
                    'can_record_external_refund' => $payment->mode === PaymentMode::HostedCheckout
                        && $payment->status === PaymentStatus::Paid,
                ])->all(),
        ];
    }

    /** @return Builder<Booking> */
    private function qualifiedMarketplaceBookings(): Builder
    {
        return Booking::query()
            ->where('source', BookingSource::Marketplace)
            ->where('status', BookingStatus::Confirmed)
            ->where(function (Builder $query): void {
                $query->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', [
                        PaymentStatus::Failed,
                        PaymentStatus::Cancelled,
                        PaymentStatus::Refunded,
                    ]);
            });
    }

    /** @return array<string, mixed> */
    private function rulePayload(PlatformServiceFeeRule $rule): array
    {
        return [
            'id' => $rule->getKey(),
            'name' => $rule->name,
            'fee_type' => $rule->fee_type->value,
            'fee_type_label' => $rule->fee_type->label(),
            'summary' => $rule->summary(),
            'percentage_rate' => $rule->percentage_basis_points === null
                ? null
                : number_format($rule->percentage_basis_points / 100, 2, '.', ''),
            'percentage_basis_points' => $rule->percentage_basis_points,
            'fixed_amount' => $rule->fixed_amount,
            'minimum_fee_amount' => $rule->minimum_fee_amount,
            'maximum_fee_amount' => $rule->maximum_fee_amount,
            'currency' => $rule->currency,
            'is_active' => $rule->is_active,
            'starts_at' => $rule->starts_at?->toIso8601String(),
            'ends_at' => $rule->ends_at?->toIso8601String(),
            'deactivated_at' => $rule->deactivated_at?->toIso8601String(),
            'created_by' => $rule->createdBy?->name,
            'created_at' => $rule->created_at?->toDateString(),
        ];
    }

    private function pauseActiveRules(?int $exceptId = null): void
    {
        PlatformServiceFeeRule::query()
            ->where('is_active', true)
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function basisPoints(string $percentageRate): int
    {
        return (int) round(((float) $percentageRate) * 100);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
