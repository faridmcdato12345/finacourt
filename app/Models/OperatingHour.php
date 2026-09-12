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

    public function isOpen24Hours(): bool
    {
        return ! $this->is_closed
            && filled($this->opens_at)
            && filled($this->closes_at)
            && substr($this->opens_at, 0, 5) === substr($this->closes_at, 0, 5);
    }

    public function spansMidnight(): bool
    {
        return ! $this->isOpen24Hours()
            && ! $this->is_closed
            && filled($this->opens_at)
            && filled($this->closes_at)
            && substr($this->closes_at, 0, 5) < substr($this->opens_at, 0, 5);
    }

    public function displayHours(): string
    {
        if ($this->is_closed || ! $this->opens_at || ! $this->closes_at) {
            return 'Closed';
        }

        if ($this->isOpen24Hours()) {
            return 'Open 24 hours';
        }

        return substr($this->opens_at, 0, 5).'–'.substr($this->closes_at, 0, 5)
            .($this->spansMidnight() ? ' next day' : '');
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => Weekday::class,
            'is_closed' => 'boolean',
        ];
    }
}
