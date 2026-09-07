<?php

namespace App\Http\Controllers\Owner;

use App\CustomerReactivation\CustomerBookingHistory;
use App\CustomerReactivation\ReactivationReport;
use App\CustomerReactivation\SendReactivationCampaign;
use App\Enums\ReactivationCampaignStatus;
use App\Enums\ReactivationSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReactivationCampaignRequest;
use App\Models\ReactivationCampaign;
use App\Models\Sport;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
        ]);
    }

    public function store(
        StoreReactivationCampaignRequest $request,
        TenantContext $context,
    ): RedirectResponse {
        $campaign = $context->organization()->reactivationCampaigns()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->getKey(),
            'campaign_token' => 'RETURN-'.Str::upper(Str::random(20)),
            'channel' => 'in_app,email',
            'status' => ReactivationCampaignStatus::Draft,
        ]);

        return redirect()->route('owner.reactivation.show', $campaign)
            ->with('status', 'Message saved as a draft. Review who can receive it before sending.');
    }

    public function show(
        ReactivationCampaign $campaign,
        TenantContext $context,
        SendReactivationCampaign $sender,
    ): Response {
        $this->authorizeTenant($campaign, $context);
        Gate::authorize('view', $campaign);
        $campaign->load(['venue:id,name', 'sport:id,name']);
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
                'suppressed' => $campaign->suppressed_count,
                'suppression_reasons' => $suppressionReasons,
                'clicks' => $campaign->recipients()->whereNotNull('clicked_at')->count(),
                'sent_at' => $campaign->sent_at?->toIso8601String(),
            ],
            'eligibility' => $campaign->status === ReactivationCampaignStatus::Draft
                ? $sender->preview($campaign)
                : null,
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
}
