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

    public function displayHours(bool $useTwelveHourClock = false): string
    {
        if ($this->is_closed || ! $this->opens_at || ! $this->closes_at) {
            return 'Closed';
        }

        if ($this->isOpen24Hours()) {
            return 'Open 24 hours';
        }

        $opensAt = $useTwelveHourClock
            ? $this->displayClockTime($this->opens_at)
            : substr($this->opens_at, 0, 5);
        $closesAt = $useTwelveHourClock
            ? $this->displayClockTime($this->closes_at)
            : substr($this->closes_at, 0, 5);

        return $opensAt.'–'.$closesAt
            .($this->spansMidnight() ? ' next day' : '');
    }

    private function displayClockTime(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));
        $suffix = $hour >= 12 ? 'PM' : 'AM';
        $displayHour = $hour % 12;

        return ($displayHour === 0 ? 12 : $displayHour).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT).' '.$suffix;
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => Weekday::class,
            'is_closed' => 'boolean',
        ];
    }
}
