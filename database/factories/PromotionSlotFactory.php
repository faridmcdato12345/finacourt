<?php

namespace Database\Factories;

use App\Models\CourtResource;
use App\Models\Promotion;
use App\Models\PromotionSlot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PromotionSlot> */
class PromotionSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'resource_id' => null,
            'slot_token' => 'SLOT-'.Str::upper(Str::random(20)),
            'slot_date' => now()->addDays(2)->toDateString(),
            'starts_at_time' => '18:00',
            'ends_at_time' => '19:00',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PromotionSlot $slot): void {
            if ($slot->resource_id !== null) {
                return;
            }

            $promotion = $slot->promotion;
            $slot->resource_id = $promotion->venue->resources()->value('id')
                ?? CourtResource::factory()->for($promotion->venue)->create()->getKey();
        });
    }
}
