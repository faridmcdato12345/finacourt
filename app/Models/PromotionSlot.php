<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PromotionSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promotion_id',
    'resource_id',
    'slot_token',
    'slot_date',
    'starts_at_time',
    'ends_at_time',
])]
class PromotionSlot extends Model
{
    /** @use HasFactory<PromotionSlotFactory> */
    use HasFactory;

    public function contains(CourtResource $resource, CarbonInterface $localStart, CarbonInterface $localEnd): bool
    {
        return $this->resource_id === $resource->getKey()
            && $this->slot_date->toDateString() === $localStart->toDateString()
            && $localStart->format('H:i:s') >= $this->starts_at_time
            && $localEnd->format('H:i:s') <= $this->ends_at_time;
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<CourtResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(CourtResource::class, 'resource_id');
    }

    protected function casts(): array
    {
        return [
            'slot_date' => 'immutable_date',
        ];
    }
}
