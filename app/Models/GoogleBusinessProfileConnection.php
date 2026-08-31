<?php

namespace App\Models;

use App\Enums\GoogleBusinessProfileConnectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'venue_id', 'authorized_by_user_id', 'status', 'match_outcome',
    'google_account_name', 'google_location_name', 'google_account_label', 'google_account_verification_state',
    'google_location_title', 'google_location_address', 'access_token', 'refresh_token',
    'token_expires_at', 'scopes', 'candidates', 'discovery_generation', 'last_error_code', 'last_error_message',
    'authorized_at', 'last_discovered_at', 'connected_at', 'disconnected_at',
])]
#[Hidden(['access_token', 'refresh_token', 'discovery_generation'])]
class GoogleBusinessProfileConnection extends Model
{
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
    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    /** @return HasMany<GoogleBusinessProfileAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(GoogleBusinessProfileAudit::class, 'connection_id');
    }

    protected function casts(): array
    {
        return [
            'status' => GoogleBusinessProfileConnectionStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'immutable_datetime',
            'scopes' => 'array',
            'candidates' => 'array',
            'authorized_at' => 'immutable_datetime',
            'last_discovered_at' => 'immutable_datetime',
            'connected_at' => 'immutable_datetime',
            'disconnected_at' => 'immutable_datetime',
        ];
    }
}
