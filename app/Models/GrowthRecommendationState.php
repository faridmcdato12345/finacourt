<?php

namespace App\Models;

use App\Enums\GrowthRecommendationStateStatus;
use App\Enums\GrowthRecommendationType;
use Database\Factories\GrowthRecommendationStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'venue_id',
    'acted_by_user_id',
    'recommendation_key',
    'recommendation_type',
    'status',
    'snoozed_until',
])]
class GrowthRecommendationState extends Model
{
    /** @use HasFactory<GrowthRecommendationStateFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'recommendation_type' => GrowthRecommendationType::class,
            'status' => GrowthRecommendationStateStatus::class,
            'snoozed_until' => 'immutable_datetime',
        ];
    }
}
