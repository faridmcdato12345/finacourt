<?php

namespace App\Models;

use Database\Factories\VenuePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_id', 'storage_path', 'alt_text', 'sort_order', 'is_primary'])]
class VenuePhoto extends Model
{
    /** @use HasFactory<VenuePhotoFactory> */
    use HasFactory;

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
