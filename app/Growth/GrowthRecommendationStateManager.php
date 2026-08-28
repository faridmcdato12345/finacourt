<?php

namespace App\Growth;

use App\Enums\GrowthRecommendationStateStatus;
use App\Models\GrowthRecommendationState;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GrowthRecommendationStateManager
{
    /**
     * @param  Collection<int, GrowthRecommendation>  $recommendations
     * @return array{active: Collection<int, GrowthRecommendation>, suppressed: Collection<int, GrowthRecommendation>}
     */
    public function apply(
        Organization $organization,
        Collection $recommendations,
        CarbonImmutable $at,
    ): array {
        $states = GrowthRecommendationState::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('recommendation_key', $recommendations->pluck('key'))
            ->get()
            ->keyBy('recommendation_key');
        $active = collect();
        $suppressed = collect();

        foreach ($recommendations as $recommendation) {
            $state = $states->get($recommendation->key);

            if ($state === null
                || ($state->status === GrowthRecommendationStateStatus::Snoozed
                    && $state->snoozed_until?->lessThanOrEqualTo($at))) {
                $active->push($recommendation);

                continue;
            }

            $suppressed->push($recommendation->withState($state->status, $state->snoozed_until));
        }

        return ['active' => $active, 'suppressed' => $suppressed];
    }

    public function set(
        Organization $organization,
        GrowthRecommendation $recommendation,
        User $actor,
        GrowthRecommendationStateStatus $status,
        ?int $snoozeDays = null,
    ): GrowthRecommendationState {
        $snoozedUntil = $status === GrowthRecommendationStateStatus::Snoozed
            ? now('UTC')->addDays($snoozeDays ?? 7)
            : null;

        return GrowthRecommendationState::query()->updateOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'recommendation_key' => $recommendation->key,
            ],
            [
                'venue_id' => $recommendation->venueId,
                'acted_by_user_id' => $actor->getKey(),
                'recommendation_type' => $recommendation->type,
                'status' => $status,
                'snoozed_until' => $snoozedUntil,
            ],
        );
    }

    public function restore(Organization $organization, string $key): bool
    {
        return GrowthRecommendationState::query()
            ->where('organization_id', $organization->getKey())
            ->where('recommendation_key', $key)
            ->delete() > 0;
    }
}
