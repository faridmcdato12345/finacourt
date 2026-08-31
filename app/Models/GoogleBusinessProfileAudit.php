<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'venue_id', 'connection_id', 'actor_user_id', 'event_type', 'context', 'occurred_at'])]
class GoogleBusinessProfileAudit extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<GoogleBusinessProfileConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleBusinessProfileConnection::class, 'connection_id');
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
