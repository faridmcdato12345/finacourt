<?php

namespace App\Models;

use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'is_active'])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    /** @return BelongsToMany<Venue, $this> */
    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class)->withTimestamps();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
