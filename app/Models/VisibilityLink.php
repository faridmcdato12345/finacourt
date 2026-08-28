<?php

namespace App\Models;

use App\Enums\VisibilityLinkDestination;
use Database\Factories\VisibilityLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'venue_id',
    'promotion_id',
    'created_by_user_id',
    'destination',
    'link_key',
    'token',
    'is_active',
    'visits_count',
    'last_visited_at',
])]
class VisibilityLink extends Model
{
    /** @use HasFactory<VisibilityLinkFactory> */
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

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'destination' => VisibilityLinkDestination::class,
            'is_active' => 'boolean',
            'visits_count' => 'integer',
            'last_visited_at' => 'immutable_datetime',
        ];
    }
}
