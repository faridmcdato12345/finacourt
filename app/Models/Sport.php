<?php

namespace App\Models;

use Database\Factories\SportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'is_active'])]
class Sport extends Model
{
    /** @use HasFactory<SportFactory> */
    use HasFactory;

    /** @return BelongsToMany<Venue, $this> */
    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class)->withTimestamps();
    }

    /** @return HasMany<CourtResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(CourtResource::class);
    }

    /** @return BelongsToMany<VenueDirectoryListing, $this> */
    public function directoryListings(): BelongsToMany
    {
        return $this->belongsToMany(VenueDirectoryListing::class, 'sport_venue_directory_listing')->withTimestamps();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
