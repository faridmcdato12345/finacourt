<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\VenueReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'venue_id',
    'resource_id',
    'booking_id',
    'player_user_id',
    'rating',
    'body',
    'status',
    'moderated_by_user_id',
    'moderated_at',
    'published_at',
    'moderation_note',
])]
class VenueReview extends Model
{
    /** @use HasFactory<VenueReviewFactory> */
    use HasFactory;

    /** @param Builder<VenueReview> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ReviewStatus::Published)
            ->whereNotNull('published_at');
    }

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

    /** @return BelongsTo<CourtResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(CourtResource::class, 'resource_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    public function reviewerDisplayName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->player?->name)) ?: [];
        $first = $parts[0] ?? 'Player';
        $lastInitial = isset($parts[1]) ? ' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : '';

        return $first.$lastInitial;
    }

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'moderated_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
