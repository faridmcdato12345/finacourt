<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'venue_id', 'player_user_id', 'reference', 'status', 'expires_at'])]
class ReservationGroup extends Model
{
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function player(): BelongsTo { return $this->belongsTo(User::class, 'player_user_id'); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    protected function casts(): array { return ['expires_at' => 'immutable_datetime']; }
}
