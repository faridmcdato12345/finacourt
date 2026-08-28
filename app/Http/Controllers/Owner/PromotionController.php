<?php

namespace App\Http\Controllers\Owner;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavePromotionRequest;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Venue;
use App\Promotions\EmptySlotFinder;
use App\Promotions\PromotionLifecycle;
use App\Promotions\PromotionSlotSynchronizer;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(TenantContext $context, EmptySlotFinder $emptySlots): Response
    {
        Gate::authorize('viewAny', [Promotion::class, $context->organization()]);

        $paginator = $context->organization()->promotions()
            ->with([
                'venue:id,organization_id,name,slug',
                'venue.organization:id,timezone',
                'resource:id,name',
                'audienceSport:id,name',
            ])
            ->withCount(['bookings', 'slots'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $promotions = $paginator->getCollection()
            ->map(fn (Promotion $promotion) => $this->payload($promotion));

        return Inertia::render('Owner/Promotions/Index', [
            'promotions' => $promotions,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'previous' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'opportunities' => $emptySlots->upcoming($context->organization(), horizonDays: 2, limit: 6),
        ]);
    }

    public function create(Request $request, TenantContext $context, EmptySlotFinder $emptySlots): Response
    {
        Gate::authorize('create', [Promotion::class, $context->organization()]);

        $opportunities = $emptySlots->upcoming($context->organization(), horizonDays: 14, limit: 80);
        $selected = $this->selectedOpportunity($request, $opportunities);
        $now = now($context->organization()->timezone);

        return Inertia::render('Owner/Promotions/Create', [
            ...$this->options($context->organization(), $opportunities),
            'defaults' => [
                'goal' => $selected && $selected['is_last_minute']
                    ? PromotionGoal::PromoteTodayOrTonight->value
                    : PromotionGoal::FillEmptySlots->value,
                'status' => PromotionStatus::Draft->value,
                'promotion_type' => $selected ? PromotionType::SpecificSlots->value : PromotionType::Deal->value,
                'venue_id' => $selected['venue_id'] ?? null,
                'resource_id' => $selected['resource_id'] ?? null,
                'audience_sport_id' => $selected['sport_id'] ?? null,
                'starts_on' => $selected['slot_date'] ?? $now->toDateString(),
                'ends_on' => $selected['slot_date'] ?? $now->addMonth()->toDateString(),
                'slots' => $selected ? [[
                    'resource_id' => $selected['resource_id'],
                    'slot_date' => $selected['slot_date'],
                    'starts_at_time' => $selected['starts_at_time'],
                    'ends_at_time' => $selected['ends_at_time'],
                ]] : [],
            ],
        ]);
    }

    public function store(
        SavePromotionRequest $request,
        TenantContext $context,
        PromotionLifecycle $lifecycle,
        PromotionSlotSynchronizer $slotSynchronizer,
    ): RedirectResponse {
        [$attributes, $slots] = $this->attributes($request);
        $status = $lifecycle->target($request->validated());
        $lifecycle->ensureTransition(null, $status);
        $promotion = DB::transaction(function () use (
            $attributes,
            $context,
            $lifecycle,
            $slotSynchronizer,
            $slots,
            $status,
        ): Promotion {
            $promotion = $context->organization()->promotions()->create([
                ...$attributes,
                ...$lifecycle->attributes($status),
                'campaign_token' => $this->campaignToken(),
            ]);
            $slotSynchronizer->sync($promotion, $slots);

            return $promotion;
        });

        return redirect()->route('owner.promotions.show', $promotion)
            ->with('status', 'Deal created. Review how it looks before sharing it.');
    }

    public function show(Promotion $promotion): Response
    {
        Gate::authorize('view', $promotion);
        $promotion->load([
            'venue.organization:id,timezone',
            'resource:id,name,base_hourly_rate,currency',
            'audienceSport:id,name,slug',
            'slots.resource:id,venue_id,sport_id,name,base_hourly_rate,currency',
            'slots.resource.sport:id,name,slug',
        ]);

        return Inertia::render('Owner/Promotions/Show', [
            'promotion' => $this->payload($promotion),
            'public_url' => route('marketplace.venues.show', [
                'venueSlug' => $promotion->venue->slug,
                ...$promotion->marketplaceParameters(),
            ]),
        ]);
    }

    public function edit(
        Promotion $promotion,
        TenantContext $context,
        EmptySlotFinder $emptySlots,
    ): Response {
        Gate::authorize('update', $promotion);

        $promotion->load([
            'venue.organization:id,timezone',
            'resource:id,name',
            'audienceSport:id,name',
            'slots.resource:id,venue_id,sport_id,name',
            'slots.resource.sport:id,name',
        ]);

        return Inertia::render('Owner/Promotions/Edit', [
            ...$this->options(
                $promotion->organization,
                $emptySlots->upcoming($promotion->organization, horizonDays: 14, limit: 80),
                $promotion,
            ),
            'promotion' => $this->payload($promotion),
        ]);
    }

    public function update(
        SavePromotionRequest $request,
        Promotion $promotion,
        PromotionLifecycle $lifecycle,
        PromotionSlotSynchronizer $slotSynchronizer,
    ): RedirectResponse {
        [$attributes, $slots] = $this->attributes($request);
        $status = $lifecycle->target($request->validated(), $promotion);
        $lifecycle->ensureTransition($promotion, $status);

        DB::transaction(function () use (
            $attributes,
            $lifecycle,
            $promotion,
            $slotSynchronizer,
            $slots,
            $status,
        ): void {
            $promotion->update([
                ...$attributes,
                ...$lifecycle->attributes($status),
            ]);
            $slotSynchronizer->sync($promotion, $slots);
        });

        return redirect()->route('owner.promotions.show', $promotion)
            ->with('status', 'Deal updated. Existing bookings kept their original prices.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        Gate::authorize('delete', $promotion);

        if ($promotion->bookings()->exists()) {
            return back()->with('status', 'Deals with bookings cannot be deleted. Turn the deal off instead.');
        }

        $promotion->delete();

        return redirect()->route('owner.promotions.index')->with('status', 'Deal deleted.');
    }

    /** @return array<string, mixed> */
    private function options(
        Organization $organization,
        ?Collection $opportunities = null,
        ?Promotion $promotion = null,
    ): array {
        $venues = $organization->venues()
            ->with(['resources' => fn ($query) => $query->with('sport:id,name,slug')->orderBy('name')])
            ->orderBy('name')
            ->get();

        return [
            'venues' => $venues->map(fn ($venue) => [
                'id' => $venue->getKey(),
                'name' => $venue->name,
                'resources' => $venue->resources->map(fn ($resource) => [
                    'id' => $resource->getKey(),
                    'name' => $resource->name,
                    'base_hourly_rate' => $resource->base_hourly_rate,
                    'currency' => $resource->currency,
                    'is_active' => $resource->is_active,
                    'sport_id' => $resource->sport_id,
                    'sport' => $resource->sport->name,
                ]),
            ]),
            'types' => collect(PromotionType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'discountTypes' => collect(PromotionDiscountType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'goals' => collect(PromotionGoal::cases())->map(fn ($goal) => [
                'value' => $goal->value,
                'label' => $goal->label(),
            ]),
            'statuses' => collect(PromotionStatus::cases())
                ->filter(fn ($status) => $promotion
                    ? $promotion->status->canTransitionTo($status)
                    : in_array($status, [
                        PromotionStatus::Draft,
                        PromotionStatus::Scheduled,
                        PromotionStatus::Active,
                    ], true))
                ->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values(),
            'weekdays' => collect(Weekday::cases())->map(fn ($day) => [
                'value' => $day->value,
                'label' => $day->label(),
            ]),
            'opportunities' => $opportunities ?? collect(),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function attributes(SavePromotionRequest $request): array
    {
        $data = $request->validated();
        $data['resource_id'] = $data['resource_id'] ?? null;
        $data['audience_sport_id'] = $data['audience_sport_id'] ?? null;
        $data['audience_city_slug'] = Venue::query()
            ->whereKey($data['venue_id'])
            ->value('city_slug');
        $data['goal'] = $data['goal'] ?? PromotionGoal::FillEmptySlots->value;
        $data['discount_type'] = $data['discount_type'] ?? null;
        $data['discount_value'] = $data['discount_value'] ?? null;
        $data['days_of_week'] = empty($data['days_of_week']) ? null : array_map('intval', $data['days_of_week']);
        $data['starts_at_time'] = $data['starts_at_time'] ?? null;
        $data['ends_at_time'] = $data['ends_at_time'] ?? null;
        $slots = $data['slots'] ?? [];
        unset($data['slots'], $data['status'], $data['is_active']);

        return [$data, $slots];
    }

    /** @return array<string, mixed> */
    private function payload(Promotion $promotion): array
    {
        return [
            'id' => $promotion->getKey(),
            'campaign_token' => $promotion->campaign_token,
            'title' => $promotion->title,
            'description' => $promotion->description,
            'promotion_type' => $promotion->promotion_type->value,
            'promotion_type_label' => $promotion->promotion_type->label(),
            'goal' => $promotion->goal->value,
            'goal_label' => $promotion->goal->label(),
            'status' => $promotion->status->value,
            'status_label' => $promotion->status->label(),
            'effective_status' => $promotion->effectiveStatus()->value,
            'effective_status_label' => $promotion->effectiveStatus()->label(),
            'discount_type' => $promotion->discount_type?->value,
            'discount_type_label' => $promotion->discount_type?->label(),
            'discount_value' => $promotion->discount_value,
            'offer_label' => $promotion->offerLabel(),
            'venue_id' => $promotion->venue_id,
            'venue' => $promotion->venue?->name,
            'resource_id' => $promotion->resource_id,
            'resource' => $promotion->resource?->name,
            'audience_sport_id' => $promotion->audience_sport_id,
            'audience_sport' => $promotion->audienceSport?->name,
            'audience_city_slug' => $promotion->audience_city_slug,
            'starts_on' => $promotion->starts_on->toDateString(),
            'ends_on' => $promotion->ends_on->toDateString(),
            'days_of_week' => $promotion->days_of_week ?? [],
            'starts_at_time' => $promotion->starts_at_time ? substr($promotion->starts_at_time, 0, 5) : null,
            'ends_at_time' => $promotion->ends_at_time ? substr($promotion->ends_at_time, 0, 5) : null,
            'targets_specific_slots' => $promotion->targets_specific_slots,
            'slots_count' => $promotion->slots_count ?? ($promotion->relationLoaded('slots')
                ? $promotion->slots->count()
                : $promotion->slots()->count()),
            'slots' => $promotion->relationLoaded('slots')
                ? $promotion->slots->map(fn ($slot) => [
                    'id' => $slot->getKey(),
                    'slot_token' => $slot->slot_token,
                    'resource_id' => $slot->resource_id,
                    'resource' => $slot->resource->name,
                    'sport' => $slot->resource->sport?->name,
                    'slot_date' => $slot->slot_date->toDateString(),
                    'starts_at_time' => substr($slot->starts_at_time, 0, 5),
                    'ends_at_time' => substr($slot->ends_at_time, 0, 5),
                ])->values()
                : [],
            'is_active' => $promotion->is_active,
            'is_public' => $promotion->is_public,
            'impressions_count' => $promotion->impressions_count,
            'clicks_count' => $promotion->clicks_count,
            'booking_starts_count' => $promotion->booking_starts_count,
            'bookings_count' => $promotion->bookings_count ?? $promotion->bookings()->count(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $opportunities
     * @return array<string, mixed>|null
     */
    private function selectedOpportunity(Request $request, Collection $opportunities): ?array
    {
        if (! $request->filled(['resource', 'date', 'start', 'end'])) {
            return null;
        }

        return $opportunities->first(fn (array $slot) => $slot['resource_id'] === $request->integer('resource')
            && $slot['slot_date'] === $request->string('date')->toString()
            && $slot['starts_at_time'] === $request->string('start')->toString()
            && $slot['ends_at_time'] === $request->string('end')->toString());
    }

    private function campaignToken(): string
    {
        do {
            $token = 'DEAL-'.Str::upper(Str::random(20));
        } while (Promotion::query()->where('campaign_token', $token)->exists());

        return $token;
    }
}
