<?php

namespace App\Http\Controllers\Owner;

use App\CustomerReactivation\CustomerBookingHistory;
use App\CustomerReactivation\ReactivationReport;
use App\CustomerReactivation\SendReactivationCampaign;
use App\Enums\ReactivationCampaignStatus;
use App\Models\CustomerOfferCampaign;
use App\Enums\ReactivationSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReactivationCampaignRequest;
use App\Models\ReactivationCampaign;
use App\Models\Sport;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReactivationCampaignController extends Controller
{
    public function index(
        TenantContext $context,
        CustomerBookingHistory $history,
        ReactivationReport $report,
    ): Response {
        $organization = $context->organization();
        Gate::authorize('viewAny', [ReactivationCampaign::class, $organization]);
        $counts = $history->segmentCounts($organization);
        $sports = Sport::query()
            ->whereHas('resources.venue', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Owner/Reactivation/Index', [
            'report' => $report->forOrganization($organization),
            'segments' => [
                ...$counts,
                'sports' => $sports,
            ],
            'rules' => [
                'inactive_days' => (int) config('reactivation.inactive_days'),
                'frequency_cooldown_days' => (int) config('reactivation.frequency_cooldown_days'),
            ],
        ]);
    }

    public function create(Request $request, TenantContext $context): Response
    {
        $organization = $context->organization();
        Gate::authorize('create', [ReactivationCampaign::class, $organization]);
        $venues = $organization->venues()
            ->with(['resources.sport:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($venue) => [
                'id' => $venue->getKey(),
                'name' => $venue->name,
                'sports' => $venue->resources->pluck('sport')->filter()->unique('id')->values(),
            ]);

        $defaultVenue = $venues->firstWhere('id', $request->integer('venue')) ?? $venues->first();
        $defaultSegment = ReactivationSegment::tryFrom($request->string('segment')->toString())
            ?? ReactivationSegment::Inactive30;

        return Inertia::render('Owner/Reactivation/Create', [
            'venues' => $venues,
            'segments' => collect(ReactivationSegment::cases())->map(fn ($segment) => [
                'value' => $segment->value,
                'label' => $segment->label(),
            ]),
            'defaults' => [
                'venue_id' => $defaultVenue['id'] ?? null,
                'segment' => $defaultSegment->value,
            ],
            'campaign' => null,
        ]);
    }

    public function edit(ReactivationCampaign $campaign, TenantContext $context): Response
    {
        $this->authorizeTenant($campaign, $context);
        Gate::authorize('update', $campaign);
        abort_unless(in_array($campaign->status, [ReactivationCampaignStatus::Draft, ReactivationCampaignStatus::Active], true), 404);

        $venues = $context->organization()->venues()
            ->with(['resources.sport:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($venue) => [
                'id' => $venue->getKey(),
                'name' => $venue->name,
                'sports' => $venue->resources->pluck('sport')->filter()->unique('id')->values(),
            ]);
        $campaign->load('offerCampaign');
        $offer = $campaign->offerCampaign;

        return Inertia::render('Owner/Reactivation/Create', [
            'venues' => $venues,
            'segments' => collect(ReactivationSegment::cases())->map(fn ($segment) => [
                'value' => $segment->value,
                'label' => $segment->label(),
            ]),
            'defaults' => [
                'venue_id' => $campaign->venue_id,
                'segment' => $campaign->segment->value,
            ],
            'campaign' => [
                'id' => $campaign->getKey(),
                'venue_id' => $campaign->venue_id,
                'sport_id' => $campaign->sport_id,
                'title' => $campaign->title,
                'message' => $campaign->message,
                'segment' => $campaign->segment->value,
                'delivery_mode' => $campaign->delivery_mode,
                'automation_inactivity_days' => $campaign->inactivity_days,
                'include_exclusive_offer' => $offer !== null && $offer->is_active,
                'offer_discount_type' => $offer?->discount_type->value,
                'offer_discount_value' => $offer?->discount_value,
                'offer_minimum_spend' => $offer?->minimum_spend,
                'offer_valid_days' => $offer?->valid_days,
                'offer_max_redemptions' => $offer?->max_redemptions,
                'offer_locked' => $offer?->offers()->exists() ?? false,
            ],
        ]);
    }

    public function store(
        StoreReactivationCampaignRequest $request,
        TenantContext $context,
    ): RedirectResponse {
        $validated = $request->validated();
        $campaign = DB::transaction(function () use ($context, $request, $validated): ReactivationCampaign {
            $campaign = $context->organization()->reactivationCampaigns()->create([
                ...collect($validated)->except([
                    'include_exclusive_offer', 'offer_discount_type', 'offer_discount_value',
                    'offer_minimum_spend', 'offer_valid_days', 'offer_max_redemptions',
                    'delivery_mode', 'automation_inactivity_days',
                ])->all(),
                'created_by_user_id' => $request->user()->getKey(),
                'campaign_token' => 'RETURN-'.Str::upper(Str::random(20)),
                'channel' => 'in_app,email',
                'delivery_mode' => $validated['delivery_mode'] ?? 'manual',
                'automation_enabled' => ($validated['delivery_mode'] ?? 'manual') === 'automated',
                'inactivity_days' => ($validated['delivery_mode'] ?? 'manual') === 'automated'
                    ? $validated['automation_inactivity_days'] : null,
                'status' => ($validated['delivery_mode'] ?? 'manual') === 'automated'
                    ? ReactivationCampaignStatus::Active
                    : ReactivationCampaignStatus::Draft,
            ]);

            if (filter_var($validated['include_exclusive_offer'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                CustomerOfferCampaign::query()->create([
                    'organization_id' => $campaign->organization_id,
                    'venue_id' => $campaign->venue_id,
                    'reactivation_campaign_id' => $campaign->getKey(),
                    'name' => 'Welcome-back offer: '.$campaign->title,
                    'discount_type' => $validated['offer_discount_type'],
                    'discount_value' => $validated['offer_discount_value'],
                    'minimum_spend' => $validated['offer_minimum_spend'] ?? null,
                    'valid_days' => $validated['offer_valid_days'],
                    'max_redemptions' => $validated['offer_max_redemptions'] ?? null,
                    'is_active' => true,
                ]);
            }

            return $campaign;
        });

        return redirect()->route('owner.reactivation.show', $campaign)
            ->with('status', 'Message saved as a draft. Review who can receive it before sending.');
    }

    public function update(
        StoreReactivationCampaignRequest $request,
        ReactivationCampaign $campaign,
        TenantContext $context,
    ): RedirectResponse {
        $this->authorizeTenant($campaign, $context);
        Gate::authorize('update', $campaign);
        $validated = $request->validated();

        $campaign = DB::transaction(function () use ($campaign, $validated): ReactivationCampaign {
            $campaign = ReactivationCampaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($campaign->status, [ReactivationCampaignStatus::Draft, ReactivationCampaignStatus::Active], true)) {
                throw ValidationException::withMessages(['campaign' => 'Only a draft or active automated campaign can be edited.']);
            }

            $mode = $validated['delivery_mode'] ?? 'manual';
            $hasRecipients = $campaign->recipients()->exists();
            if ($hasRecipients && $mode !== $campaign->delivery_mode) {
                throw ValidationException::withMessages(['delivery_mode' => 'The delivery mode cannot change after recipients have been created. Create a new campaign instead.']);
            }
            if ($hasRecipients && (int) $validated['venue_id'] !== $campaign->venue_id) {
                throw ValidationException::withMessages(['venue_id' => 'The venue cannot change after recipients have been created. Create a new campaign instead.']);
            }

            $offer = $campaign->offerCampaign()->lockForUpdate()->first();
            $offerLocked = $offer?->offers()->exists() ?? false;
            $wantsOffer = filter_var($validated['include_exclusive_offer'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($offerLocked && $wantsOffer && $this->offerTermsChanged($offer, $validated)) {
                throw ValidationException::withMessages(['offer_discount_value' => 'Offer terms are locked because customer-specific offers have already been issued.']);
            }

            $campaign->update([
                'venue_id' => $validated['venue_id'],
                'sport_id' => $validated['sport_id'] ?? null,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'segment' => $validated['segment'],
                'delivery_mode' => $mode,
                'automation_enabled' => $mode === 'automated',
                'inactivity_days' => $mode === 'automated' ? $validated['automation_inactivity_days'] : null,
                'status' => $mode === 'automated' ? ReactivationCampaignStatus::Active : ReactivationCampaignStatus::Draft,
            ]);

            if ($wantsOffer && $offer === null) {
                $offer = CustomerOfferCampaign::query()->create($this->offerCampaignAttributes($campaign, $validated));
            } elseif ($wantsOffer && $offer !== null && ! $offerLocked) {
                $offer->update($this->offerCampaignAttributes($campaign, $validated));
            } elseif ($wantsOffer && $offer !== null) {
                $offer->update(['is_active' => true]);
            } elseif (! $wantsOffer && $offer !== null) {
                // This stops future issuance but leaves already-issued offers
                // under their original terms. Individual offers can still be
                // revoked explicitly without changing historical messages.
                $offer->update(['is_active' => false]);
            }

            return $campaign;
        });

        return redirect()->route('owner.reactivation.show', $campaign)
            ->with('status', $campaign->delivery_mode === 'automated'
                ? 'Automated win-back updated. The next hourly check uses these settings.'
                : 'Draft message updated. Review the audience before sending.');
    }

    public function show(
        ReactivationCampaign $campaign,
        TenantContext $context,
        SendReactivationCampaign $sender,
    ): Response {
        $this->authorizeTenant($campaign, $context);
        Gate::authorize('view', $campaign);
        $campaign->load(['venue:id,name', 'sport:id,name', 'offerCampaign']);
        $suppressionReasons = $this->suppressionReasons($campaign);

        return Inertia::render('Owner/Reactivation/Show', [
            'campaign' => [
                'id' => $campaign->getKey(),
                'title' => $campaign->title,
                'message' => $campaign->message,
                'venue' => $campaign->venue->name,
                'sport' => $campaign->sport?->name,
                'segment' => $campaign->segment->value,
                'segment_label' => $campaign->segment->label(),
                'status' => $campaign->status->value,
                'status_label' => $campaign->status->label(),
                'audience' => $campaign->audience_count,
                'sent' => $campaign->sent_count,
                'delivered' => $campaign->delivered_count,
                'failed' => $campaign->failed_count,
                'suppressed' => $campaign->suppressed_count,
                'suppression_reasons' => $suppressionReasons,
                'clicks' => $campaign->recipients()->whereNotNull('clicked_at')->count(),
                'sent_at' => $campaign->sent_at?->toIso8601String(),
                'offer' => $campaign->offerCampaign === null ? null : [
                    'label' => $campaign->offerCampaign->label(),
                    'minimum_spend' => $campaign->offerCampaign->minimum_spend,
                    'valid_days' => $campaign->offerCampaign->valid_days,
                    'max_redemptions' => $campaign->offerCampaign->max_redemptions,
                ],
                'delivery_mode' => $campaign->delivery_mode,
                'automation_enabled' => $campaign->automation_enabled,
                'inactivity_days' => $campaign->inactivity_days,
            ],
            'eligibility' => match (true) {
                $campaign->status === ReactivationCampaignStatus::Draft => $sender->preview($campaign),
                $campaign->delivery_mode === 'automated' => $sender->previewAutomated($campaign),
                default => null,
            },
        ]);
    }

    public function send(
        ReactivationCampaign $campaign,
        TenantContext $context,
        SendReactivationCampaign $sender,
    ): RedirectResponse {
        $this->authorizeTenant($campaign, $context);
        Gate::authorize('send', $campaign);
        $campaign = $sender->handle($campaign)->refresh();

        return redirect()->route('owner.reactivation.show', $campaign)
            ->with('status', $this->sendResultMessage($campaign));
    }

    public function cancel(
        ReactivationCampaign $campaign,
        TenantContext $context,
    ): RedirectResponse {
        $this->authorizeTenant($campaign, $context);
        Gate::authorize('cancel', $campaign);

        if ($campaign->status !== ReactivationCampaignStatus::Draft) {
            return back()->with('status', 'Only a draft message can be cancelled.');
        }

        $campaign->update([
            'status' => ReactivationCampaignStatus::Cancelled,
            'cancelled_at' => now('UTC'),
        ]);

        return redirect()->route('owner.reactivation.index')->with('status', 'Draft message cancelled.');
    }

    public function toggleAutomation(
        ReactivationCampaign $campaign,
        TenantContext $context,
    ): RedirectResponse {
        $this->authorizeTenant($campaign, $context);
        Gate::authorize('manageAutomation', $campaign);
        abort_unless($campaign->delivery_mode === 'automated', 404);

        $campaign->update(['automation_enabled' => ! $campaign->automation_enabled]);

        return back()->with('status', $campaign->automation_enabled
            ? 'Automated win-back is active. Eligible customers are checked hourly.'
            : 'Automated win-back is paused. No new messages or offers will be issued.');
    }

    private function authorizeTenant(ReactivationCampaign $campaign, TenantContext $context): void
    {
        abort_unless($campaign->organization_id === $context->organization()->getKey(), 404);
    }

    /** @return array{marketing_opt_out: int, frequency_cooldown: int, other: int} */
    private function suppressionReasons(ReactivationCampaign $campaign): array
    {
        $grouped = $campaign->recipients()
            ->whereNotNull('suppressed_at')
            ->selectRaw('suppression_reason, COUNT(*) as aggregate')
            ->groupBy('suppression_reason')
            ->pluck('aggregate', 'suppression_reason');
        $marketingOptOut = (int) $grouped->get('marketing_opt_out', 0);
        $frequencyCooldown = (int) $grouped->get('frequency_cooldown', 0);

        return [
            'marketing_opt_out' => $marketingOptOut,
            'frequency_cooldown' => $frequencyCooldown,
            'other' => max(0, (int) $campaign->suppressed_count - $marketingOptOut - $frequencyCooldown),
        ];
    }

    private function sendResultMessage(ReactivationCampaign $campaign): string
    {
        if ($campaign->sent_count === 0) {
            $reasons = $this->suppressionReasons($campaign);

            if ($reasons['marketing_opt_out'] > 0 && $reasons['frequency_cooldown'] === 0) {
                $players = $reasons['marketing_opt_out'] === 1
                    ? 'The matching past player has'
                    : "{$reasons['marketing_opt_out']} matching past players have";

                return "No messages were sent. {$players} not enabled comeback messages in their FinACourt notification preferences.";
            }

            if ($reasons['frequency_cooldown'] > 0 && $reasons['marketing_opt_out'] === 0) {
                $players = $reasons['frequency_cooldown'] === 1
                    ? 'The matching past player is'
                    : "{$reasons['frequency_cooldown']} matching past players are";

                return "No messages were sent. {$players} still inside the contact cooldown.";
            }

            return 'No messages were sent because no matching past player is currently eligible. Review the delivery details below.';
        }

        $message = "Message queued for {$campaign->sent_count} ".Str::plural('player', $campaign->sent_count)
            .' through each player’s enabled in-app or email channels.';

        if ($campaign->suppressed_count > 0) {
            $message .= " {$campaign->suppressed_count} "
                .($campaign->suppressed_count === 1 ? 'player was' : 'players were')
                .' not included because of notification preferences or the contact cooldown.';
        }

        return $message;
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function offerCampaignAttributes(ReactivationCampaign $campaign, array $validated): array
    {
        return [
            'organization_id' => $campaign->organization_id,
            'venue_id' => $campaign->venue_id,
            'reactivation_campaign_id' => $campaign->getKey(),
            'name' => 'Welcome-back offer: '.$campaign->title,
            'discount_type' => $validated['offer_discount_type'],
            'discount_value' => $validated['offer_discount_value'],
            'minimum_spend' => $validated['offer_minimum_spend'] ?? null,
            'valid_days' => $validated['offer_valid_days'],
            'max_redemptions' => $validated['offer_max_redemptions'] ?? null,
            'is_active' => true,
        ];
    }

    /** @param array<string, mixed> $validated */
    private function offerTermsChanged(CustomerOfferCampaign $offer, array $validated): bool
    {
        return $offer->discount_type->value !== $validated['offer_discount_type']
            || $offer->discount_value !== $this->decimal((string) $validated['offer_discount_value'])
            || $offer->minimum_spend !== ($validated['offer_minimum_spend'] === null ? null : $this->decimal((string) $validated['offer_minimum_spend']))
            || $offer->valid_days !== (int) $validated['offer_valid_days']
            || $offer->max_redemptions !== ($validated['offer_max_redemptions'] === null ? null : (int) $validated['offer_max_redemptions']);
    }

    private function decimal(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
