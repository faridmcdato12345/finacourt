<?php

namespace App\Growth;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationStateStatus;
use App\Enums\GrowthRecommendationType;
use Carbon\CarbonImmutable;

readonly class GrowthRecommendation
{
    /**
     * @param  array<string, bool|float|int|string|null>  $evidence
     */
    public function __construct(
        public string $key,
        public string $rule,
        public GrowthRecommendationType $type,
        public GrowthRecommendationPriority $priority,
        public int $organizationId,
        public ?int $venueId,
        public ?string $venueName,
        public string $title,
        public string $explanation,
        public array $evidence,
        public string $actionLabel,
        public string $actionUrl,
        public CarbonImmutable $calculatedAt,
        public CarbonImmutable $expiresAt,
        public ?GrowthRecommendationStateStatus $state = null,
        public ?CarbonImmutable $snoozedUntil = null,
    ) {}

    public static function key(
        GrowthRecommendationType $type,
        int $organizationId,
        ?int $venueId,
        string $subject,
    ): string {
        return hash('sha256', implode('|', [
            'growth-recommendation-v1',
            $type->value,
            $organizationId,
            $venueId ?? 0,
            $subject,
        ]));
    }

    public function isStale(?CarbonImmutable $at = null): bool
    {
        return $this->expiresAt->lessThanOrEqualTo($at ?? CarbonImmutable::now('UTC'));
    }

    public function withState(
        ?GrowthRecommendationStateStatus $state,
        ?CarbonImmutable $snoozedUntil = null,
    ): self {
        return new self(
            key: $this->key,
            rule: $this->rule,
            type: $this->type,
            priority: $this->priority,
            organizationId: $this->organizationId,
            venueId: $this->venueId,
            venueName: $this->venueName,
            title: $this->title,
            explanation: $this->explanation,
            evidence: $this->evidence,
            actionLabel: $this->actionLabel,
            actionUrl: $this->actionUrl,
            calculatedAt: $this->calculatedAt,
            expiresAt: $this->expiresAt,
            state: $state,
            snoozedUntil: $snoozedUntil,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'rule' => $this->rule,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'priority' => $this->priority->value,
            'organization_id' => $this->organizationId,
            'venue_id' => $this->venueId,
            'venue' => $this->venueName,
            'title' => $this->title,
            'explanation' => $this->explanation,
            'evidence' => $this->evidence,
            'suggested_action' => [
                'label' => $this->actionLabel,
                'url' => $this->actionUrl,
            ],
            'calculated_at' => $this->calculatedAt->toIso8601String(),
            'expires_at' => $this->expiresAt->toIso8601String(),
            'is_stale' => $this->isStale(),
            'state' => $this->state?->value ?? 'active',
            'state_label' => $this->state?->label() ?? 'Active',
            'snoozed_until' => $this->snoozedUntil?->toIso8601String(),
        ];
    }
}
