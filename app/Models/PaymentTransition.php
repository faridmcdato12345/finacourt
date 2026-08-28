<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id',
    'from_status',
    'to_status',
    'source',
    'actor_user_id',
    'external_event_id',
    'note',
    'metadata',
])]
class PaymentTransition extends Model
{
    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'from_status' => PaymentStatus::class,
            'to_status' => PaymentStatus::class,
            'metadata' => 'array',
        ];
    }
}
