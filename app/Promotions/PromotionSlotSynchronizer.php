<?php

namespace App\Promotions;

use App\Models\CourtResource;
use App\Models\Promotion;
use App\Models\PromotionSlot;
use Illuminate\Support\Str;
use LogicException;

class PromotionSlotSynchronizer
{
    /** @param array<int, array<string, mixed>> $slots */
    public function sync(Promotion $promotion, array $slots): void
    {
        $promotion->loadMissing('venue');
        $existing = $promotion->slots()->get()->keyBy('id');
        $retained = [];

        foreach ($slots as $data) {
            $resource = CourtResource::query()
                ->whereKey($data['resource_id'])
                ->where('venue_id', $promotion->venue_id)
                ->whereHas('venue', fn ($query) => $query
                    ->where('organization_id', $promotion->organization_id))
                ->first();

            if ($resource === null) {
                throw new LogicException('Promotion slots must use resources from the campaign venue and tenant.');
            }

            $slot = filled($data['id'] ?? null)
                ? $existing->get((int) $data['id'])
                : null;

            if (filled($data['id'] ?? null) && $slot === null) {
                throw new LogicException('A promotion slot cannot be moved between campaigns.');
            }

            $slot ??= new PromotionSlot([
                'promotion_id' => $promotion->getKey(),
                'slot_token' => $this->slotToken(),
            ]);
            $slot->fill([
                'resource_id' => $resource->getKey(),
                'slot_date' => $data['slot_date'],
                'starts_at_time' => $data['starts_at_time'],
                'ends_at_time' => $data['ends_at_time'],
            ])->save();
            $retained[] = $slot->getKey();
        }

        $promotion->slots()->when(
            $retained !== [],
            fn ($query) => $query->whereNotIn('id', $retained),
        )->delete();
        $promotion->update(['targets_specific_slots' => $retained !== []]);
        $promotion->unsetRelation('slots');
    }

    private function slotToken(): string
    {
        do {
            $token = 'SLOT-'.Str::upper(Str::random(20));
        } while (PromotionSlot::query()->where('slot_token', $token)->exists());

        return $token;
    }
}
