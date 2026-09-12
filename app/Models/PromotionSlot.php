<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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
            && $localStart->greaterThanOrEqualTo($this->startsAt($localStart->getTimezone()->getName()))
            && $localEnd->lessThanOrEqualTo($this->endsAt($localStart->getTimezone()->getName()));
    }

    public function startsAt(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->slot_date->toDateString().' '.substr($this->starts_at_time, 0, 5),
            $timezone,
        );
    }

    public function endsAt(string $timezone): CarbonImmutable
    {
        $start = $this->startsAt($timezone);
        $end = CarbonImmutable::parse(
            $this->slot_date->toDateString().' '.substr($this->ends_at_time, 0, 5),
            $timezone,
        );

        return $end->lessThanOrEqualTo($start) ? $end->addDay() : $end;
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
