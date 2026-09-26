<?php

namespace App\Models;

use App\Enums\OutreachMessageStatus;
use App\Enums\OutreachMessageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'outreach_lead_id',
    'message_type',
    'status',
    'provider_message_id',
    'attempts',
    'queued_at',
    'attempted_at',
    'sent_at',
    'failed_at',
    'error_message',
])]
class OutreachMessage extends Model
{
    /** @return BelongsTo<OutreachLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(OutreachLead::class, 'outreach_lead_id');
    }

    protected function casts(): array
    {
        return [
            'message_type' => OutreachMessageType::class,
            'status' => OutreachMessageStatus::class,
            'queued_at' => 'immutable_datetime',
            'attempted_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
