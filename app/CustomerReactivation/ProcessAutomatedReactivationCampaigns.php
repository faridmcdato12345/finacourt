<?php

namespace App\CustomerReactivation;

use App\CustomerOffers\CustomerOfferService;
use App\Enums\ReactivationCampaignStatus;
use App\Models\ReactivationCampaign;
use App\Models\ReactivationCampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessAutomatedReactivationCampaigns
{
    public function __construct(
        private readonly CustomerBookingHistory $history,
        private readonly CustomerClassifier $classifier,
        private readonly RebookingSuggestion $suggestions,
        private readonly SendReactivationCampaign $manualSender,
        private readonly CustomerOfferService $offers,
    ) {}

    public function handle(): int
    {
        $created = 0;
        $campaigns = ReactivationCampaign::query()
            ->where('delivery_mode', 'automated')
            ->where('automation_enabled', true)
            ->where('status', ReactivationCampaignStatus::Active)
            ->orderBy('id')
            ->get();
        foreach ($campaigns as $campaign) {
            $created += $this->process($campaign);
        }

        return $created;
    }

    private function process(ReactivationCampaign $campaign): int
    {
        return DB::transaction(function () use ($campaign): int {
            $campaign = ReactivationCampaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();
            if (! $campaign->automation_enabled || $campaign->status !== ReactivationCampaignStatus::Active) {
                return 0;
            }

            $campaign->loadMissing(['organization', 'venue', 'offerCampaign']);
            $created = 0;
            foreach ($this->history->automatedAudience($campaign) as $member) {
                $user = $member['user'];
                $cycle = hash('sha256', implode('|', [
                    $campaign->getKey(), $campaign->venue_id, $user->getKey(), (string) $member['last_booking_at'],
                ]));
                $lifecycle = $this->classifier->classify($campaign->organization, $user);
                if ($lifecycle === null) {
                    continue;
                }

                $existing = ReactivationCampaignRecipient::query()
                    ->where('reactivation_campaign_id', $campaign->getKey())
                    ->where('user_id', $user->getKey())
                    ->where('inactivity_cycle', $cycle)
                    ->exists();
                if ($existing) {
                    continue;
                }

                $reason = $this->manualSender->suppressionReason($campaign, $user);
                $suggestion = $reason === null ? $this->suggestions->for($campaign, $user) : null;
                $recipient = ReactivationCampaignRecipient::query()->create([
                    'reactivation_campaign_id' => $campaign->getKey(),
                    'user_id' => $user->getKey(),
                    'suggested_resource_id' => $suggestion['resource_id'] ?? null,
                    'click_token' => Str::lower(Str::random(40)),
                    'lifecycle' => $lifecycle,
                    'inactivity_cycle' => $cycle,
                    'last_booking_at' => $member['last_booking_at'],
                    'suggested_date' => $suggestion['date'] ?? null,
                    'suggested_start_time' => $suggestion['start_time'] ?? null,
                    'suggested_duration_minutes' => $suggestion['duration_minutes'] ?? null,
                    'sent_at' => $reason === null ? now('UTC') : null,
                    'suppressed_at' => $reason === null ? null : now('UTC'),
                    'suppression_reason' => $reason,
                ]);
                if ($reason === null && $campaign->offerCampaign !== null) {
                    $this->offers->issue($campaign->offerCampaign, $user, $recipient->getKey());
                }
                $created++;
            }

            $campaign->update([
                'audience_count' => $campaign->recipients()->count(),
                'sent_count' => $campaign->recipients()->whereNotNull('sent_at')->count(),
                'suppressed_count' => $campaign->recipients()->whereNotNull('suppressed_at')->count(),
                'automation_last_run_at' => now('UTC'),
            ]);
            DB::afterCommit(fn () => app(DeliverReactivationCampaign::class)->handle($campaign->getKey()));

            return $created;
        }, 3);
    }
}
