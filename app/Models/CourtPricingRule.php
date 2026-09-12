<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'resource_id',
    'name',
    'days_of_week',
    'starts_at_time',
    'ends_at_time',
    'hourly_rate',
])]
class CourtPricingRule extends Model
{
    /** @return BelongsTo<CourtResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(CourtResource::class, 'resource_id');
    }

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'hourly_rate' => 'decimal:2',
        ];
    }
}
