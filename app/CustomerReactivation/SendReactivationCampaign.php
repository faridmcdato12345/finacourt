<?php

namespace App\CustomerReactivation;

use App\Enums\ReactivationCampaignStatus;
use App\Models\ReactivationCampaign;
use App\Models\ReactivationCampaignRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendReactivationCampaign
{
    public function __construct(
        private readonly CustomerBookingHistory $history,
        private readonly CustomerClassifier $classifier,
        private readonly RebookingSuggestion $suggestions,
    ) {}

    public function handle(ReactivationCampaign $campaign): ReactivationCampaign
    {
        return DB::transaction(function () use ($campaign): ReactivationCampaign {
            $campaign = ReactivationCampaign::query()
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($campaign->status !== ReactivationCampaignStatus::Draft) {
                throw ValidationException::withMessages([
                    'campaign' => 'Only a draft comeback campaign can be sent.',
                ]);
            }

            $campaign->loadMissing(['organization', 'venue']);
            $audience = $this->history->audience($campaign);
            $sent = 0;
            $suppressed = 0;

            foreach ($audience as $member) {
                /** @var User $user */
                $user = $member['user'];
                $reason = $this->suppressionReason($campaign, $user);
                $suggestion = $reason === null ? $this->suggestions->for($campaign, $user) : null;
                $lifecycle = $this->classifier->classify($campaign->organization, $user);

                if ($lifecycle === null) {
                    continue;
                }

                ReactivationCampaignRecipient::query()->create([
                    'reactivation_campaign_id' => $campaign->getKey(),
                    'user_id' => $user->getKey(),
                    'suggested_resource_id' => $suggestion['resource_id'] ?? null,
                    'click_token' => Str::lower(Str::random(40)),
                    'lifecycle' => $lifecycle,
                    'last_booking_at' => $member['last_booking_at'],
                    'suggested_date' => $suggestion['date'] ?? null,
                    'suggested_start_time' => $suggestion['start_time'] ?? null,
                    'suggested_duration_minutes' => $suggestion['duration_minutes'] ?? null,
                    'sent_at' => $reason === null ? now('UTC') : null,
                    'suppressed_at' => $reason === null ? null : now('UTC'),
                    'suppression_reason' => $reason,
                ]);

                $reason === null ? $sent++ : $suppressed++;
            }

            $campaign->update([
                'status' => ReactivationCampaignStatus::Sent,
                'audience_count' => $sent + $suppressed,
                'sent_count' => $sent,
                'suppressed_count' => $suppressed,
                'sent_at' => now('UTC'),
            ]);

            DB::afterCommit(fn () => app(DeliverReactivationCampaign::class)->handle($campaign->getKey()));

            return $campaign;
        }, 3);
    }

    private function suppressionReason(ReactivationCampaign $campaign, User $user): ?string
    {
        if (! $user->marketingPreference?->canReceiveInAppMarketing()) {
            return 'marketing_opt_out';
        }

        $recentlyContacted = ReactivationCampaignRecipient::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now('UTC')->subDays((int) config('reactivation.frequency_cooldown_days', 14)))
            ->whereHas('campaign', fn ($query) => $query->where('organization_id', $campaign->organization_id))
            ->exists();

        return $recentlyContacted ? 'frequency_cooldown' : null;
    }
}
