<?php

namespace App\Models;

use Database\Factories\MarketingPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'marketing_opt_in',
    'in_app_marketing_enabled',
    'opted_in_at',
    'opted_out_at',
    'unsubscribed_at',
])]
class MarketingPreference extends Model
{
    /** @use HasFactory<MarketingPreferenceFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canReceiveInAppMarketing(): bool
    {
        return $this->marketing_opt_in
            && $this->in_app_marketing_enabled
            && $this->unsubscribed_at === null;
    }

    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean',
            'in_app_marketing_enabled' => 'boolean',
            'opted_in_at' => 'immutable_datetime',
            'opted_out_at' => 'immutable_datetime',
            'unsubscribed_at' => 'immutable_datetime',
        ];
    }
}
