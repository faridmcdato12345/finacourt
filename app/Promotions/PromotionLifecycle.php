<?php

namespace App\Promotions;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Validation\ValidationException;

class PromotionLifecycle
{
    /** @param array<string, mixed> $data */
    public function target(array $data, ?Promotion $promotion = null): PromotionStatus
    {
        if (filled($data['status'] ?? null)) {
            return PromotionStatus::from($data['status']);
        }

        if ((bool) ($data['is_active'] ?? false)) {
            return $promotion?->status === PromotionStatus::Scheduled
                ? PromotionStatus::Scheduled
                : PromotionStatus::Active;
        }

        return $promotion?->status?->acceptsBookings() === true
            ? PromotionStatus::Paused
            : PromotionStatus::Draft;
    }

    public function ensureTransition(?Promotion $promotion, PromotionStatus $target): void
    {
        if ($promotion !== null && ! $promotion->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "A {$promotion->status->label()} campaign cannot move to {$target->label()}.",
            ]);
        }
    }

    /** @return array{status: string, is_active: bool} */
    public function attributes(PromotionStatus $status): array
    {
        return [
            'status' => $status->value,
            'is_active' => $status->acceptsBookings(),
        ];
    }
}
