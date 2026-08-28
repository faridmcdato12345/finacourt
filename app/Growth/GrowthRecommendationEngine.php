<?php

namespace App\Growth;

use App\Growth\Contracts\RecommendationRule;
use App\Growth\Rules\ChannelConversionRule;
use App\Growth\Rules\DemandWithInventoryRule;
use App\Growth\Rules\EmptyInventoryRule;
use App\Growth\Rules\InactiveCustomersRule;
use App\Growth\Rules\LowBookingConversionRule;
use App\Growth\Rules\SuccessfulCampaignRule;
use App\Growth\Rules\UnfulfilledDemandRule;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GrowthRecommendationEngine
{
    /** @var array<int, RecommendationRule> */
    private array $rules;

    public function __construct(
        private readonly GrowthEvidence $evidence,
        private readonly GrowthRecommendationStateManager $states,
        EmptyInventoryRule $emptyInventory,
        DemandWithInventoryRule $demandWithInventory,
        UnfulfilledDemandRule $unfulfilledDemand,
        InactiveCustomersRule $inactiveCustomers,
        SuccessfulCampaignRule $successfulCampaign,
        LowBookingConversionRule $lowConversion,
        ChannelConversionRule $channelConversion,
    ) {
        $this->rules = [
            $unfulfilledDemand,
            $demandWithInventory,
            $lowConversion,
            $emptyInventory,
            $inactiveCustomers,
            $successfulCampaign,
            $channelConversion,
        ];
    }

    public function report(
        Organization $organization,
        ?CarbonImmutable $at = null,
        ?int $limit = null,
    ): GrowthRecommendationReport {
        $context = new GrowthRecommendationContext($organization, $this->evidence, $at);
        $generated = $this->generated($context);
        $decorated = $this->states->apply($organization, $generated, $context->calculatedAt);
        $limit ??= max(1, (int) config('growth.owner_limit', 5));

        return new GrowthRecommendationReport(
            active: $decorated['active']->take($limit)->values(),
            suppressed: $decorated['suppressed']->take(25)->values(),
            calculatedAt: $context->calculatedAt,
            lookbackDays: $context->lookbackDays,
        );
    }

    public function findGenerated(
        Organization $organization,
        string $key,
        ?CarbonImmutable $at = null,
    ): ?GrowthRecommendation {
        $context = new GrowthRecommendationContext($organization, $this->evidence, $at);

        return $this->generated($context)->firstWhere('key', $key);
    }

    /** @return Collection<int, GrowthRecommendation> */
    private function generated(GrowthRecommendationContext $context): Collection
    {
        return collect($this->rules)
            ->flatMap(fn (RecommendationRule $rule) => $rule->evaluate($context))
            ->filter(fn (GrowthRecommendation $recommendation) => ! $recommendation->isStale($context->calculatedAt))
            ->unique('key')
            ->sort(function (GrowthRecommendation $left, GrowthRecommendation $right): int {
                $priority = $right->priority->weight() <=> $left->priority->weight();

                return $priority !== 0 ? $priority : $left->key <=> $right->key;
            })
            ->values();
    }
}
