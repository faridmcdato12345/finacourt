<?php

namespace App\Models;

use App\Enums\Weekday;
use Database\Factories\OperatingHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_id', 'day_of_week', 'is_closed', 'opens_at', 'closes_at'])]
class OperatingHour extends Model
{
    /** @use HasFactory<OperatingHourFactory> */
    use HasFactory;

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => Weekday::class,
            'is_closed' => 'boolean',
        ];
    }
}
