<?php

namespace App\Pricing;

use App\Models\CourtPricingRule;
use App\Models\CourtResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageCourtPricingRule
{
    /** @param array<string, mixed> $data */
    public function save(
        CourtResource $resource,
        array $data,
        ?CourtPricingRule $pricingRule = null,
    ): CourtPricingRule {
        return DB::transaction(function () use ($resource, $data, $pricingRule): CourtPricingRule {
            $lockedResource = CourtResource::query()
                ->whereKey($resource->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($pricingRule !== null && $pricingRule->resource_id !== $lockedResource->getKey()) {
                abort(404);
            }

            $days = collect($data['days_of_week'])
                ->map(fn ($day) => (int) $day)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $start = $data['starts_at_time'].':00';
            $end = $data['ends_at_time'].':00';

            foreach ($days as $day) {
                $conflict = $lockedResource->pricingRules()
                    ->when($pricingRule, fn ($query) => $query->whereKeyNot($pricingRule->getKey()))
                    ->whereJsonContains('days_of_week', $day)
                    ->where('starts_at_time', '<', $end)
                    ->where('ends_at_time', '>', $start)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'schedule' => 'This price overlaps another time-based price on one or more selected days.',
                    ]);
                }
            }

            $values = [
                'name' => $data['name'],
                'days_of_week' => $days,
                'starts_at_time' => $start,
                'ends_at_time' => $end,
                'hourly_rate' => $data['hourly_rate'],
            ];

            if ($pricingRule === null) {
                return $lockedResource->pricingRules()->create($values);
            }

            $lockedRule = CourtPricingRule::query()
                ->whereKey($pricingRule->getKey())
                ->where('resource_id', $lockedResource->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRule->update($values);

            return $lockedRule;
        }, 5);
    }

    public function delete(CourtResource $resource, CourtPricingRule $pricingRule): void
    {
        DB::transaction(function () use ($resource, $pricingRule): void {
            $lockedResource = CourtResource::query()
                ->whereKey($resource->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            CourtPricingRule::query()
                ->whereKey($pricingRule->getKey())
                ->where('resource_id', $lockedResource->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        }, 5);
    }
}
