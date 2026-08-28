<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'parent_code', 'name', 'level', 'type', 'source_version'])]
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
}
