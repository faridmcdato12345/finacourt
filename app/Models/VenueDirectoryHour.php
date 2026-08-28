<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_directory_listing_id', 'day_of_week', 'is_closed', 'opens_at', 'closes_at'])]
class VenueDirectoryHour extends Model
{
    /** @return BelongsTo<VenueDirectoryListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(VenueDirectoryListing::class, 'venue_directory_listing_id');
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => Weekday::class,
            'is_closed' => 'boolean',
        ];
    }
}
