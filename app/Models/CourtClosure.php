<?php

namespace App\Models;

use App\Enums\CourtClosureRefundStatus;
use App\Enums\CourtClosureStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'venue_id',
    'reference',
    'scope',
    'starts_at',
    'ends_at',
    'timezone',
    'reason',
    'status',
    'refund_status',
    'created_by_user_id',
    'approved_by_user_id',
    'approved_at',
    'reopened_by_user_id',
    'reopened_at',
    'completed_at',
])]
class CourtClosure extends Model
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(CourtResource::class, 'court_closure_resources', 'court_closure_id', 'resource_id')
            ->withTimestamps();
    }

    public function affectedBookings(): HasMany
    {
        return $this->hasMany(CourtClosureBooking::class);
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(CourtAvailabilityBlock::class);
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => CourtClosureStatus::class,
            'refund_status' => CourtClosureRefundStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
