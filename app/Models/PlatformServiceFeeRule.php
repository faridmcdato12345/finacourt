<?php

namespace App\Models;

use App\Enums\PlatformServiceFeeType;
use Carbon\CarbonInterface;
use Database\Factories\PlatformServiceFeeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'fee_type',
    'percentage_basis_points',
    'fixed_amount',
    'minimum_fee_amount',
    'maximum_fee_amount',
    'currency',
    'is_active',
    'starts_at',
    'ends_at',
    'deactivated_at',
    'created_by_user_id',
])]
class PlatformServiceFeeRule extends Model
{
    /** @use HasFactory<PlatformServiceFeeRuleFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @param Builder<PlatformServiceFeeRule> $query */
    public function scopeEffective(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $query
            ->where('is_active', true)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            });
    }

    public function summary(): string
    {
        if ($this->fee_type === PlatformServiceFeeType::Percentage) {
            $rate = number_format(($this->percentage_basis_points ?? 0) / 100, 2);

            return "{$rate}% of court price";
        }

        return '₱'.number_format((float) $this->fixed_amount, 2).' per booking';
    }

    protected function casts(): array
    {
        return [
            'fee_type' => PlatformServiceFeeType::class,
            'fixed_amount' => 'decimal:2',
            'minimum_fee_amount' => 'decimal:2',
            'maximum_fee_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
