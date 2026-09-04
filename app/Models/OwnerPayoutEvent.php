<?php

namespace App\Models;

use App\Enums\OwnerPayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'owner_payout_id', 'organization_id', 'actor_user_id', 'action',
    'from_status', 'to_status', 'note', 'metadata',
])]
class OwnerPayoutEvent extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<OwnerPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(OwnerPayout::class, 'owner_payout_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'from_status' => OwnerPayoutStatus::class,
            'to_status' => OwnerPayoutStatus::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
