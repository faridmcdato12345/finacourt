<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'parent_code', 'geographic_parent_code', 'name', 'level', 'type', 'source_version'])]
class PsgcLocation extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<PsgcLocation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_code', 'code');
    }

    /** @return HasMany<PsgcLocation, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'code');
    }

    /** @return HasMany<PsgcLocation, $this> */
    public function geographicChildren(): HasMany
    {
        return $this->hasMany(self::class, 'geographic_parent_code', 'code');
    }

    /** @param Builder<PsgcLocation> $query @return Builder<PsgcLocation> */
    public function scopeSelectableUnder(Builder $query, string $parentCode): Builder
    {
        return $query
            ->whereIn('level', ['city', 'municipality'])
            ->where(fn (Builder $query) => $query
                ->where('parent_code', $parentCode)
                ->orWhere('geographic_parent_code', $parentCode));
    }

    public function isSelectableUnder(self $parent): bool
    {
        return $this->parent_code === $parent->code
            || $this->geographic_parent_code === $parent->code;
    }
}
