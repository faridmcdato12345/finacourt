<?php

namespace App\Models;

use App\Enums\CustomerOfferDiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'venue_id', 'reactivation_campaign_id', 'name', 'discount_type',
    'discount_value', 'minimum_spend', 'valid_days', 'max_redemptions', 'is_active',
])]
class CustomerOfferCampaign extends Model
{
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function reactivationCampaign(): BelongsTo { return $this->belongsTo(ReactivationCampaign::class); }
    public function offers(): HasMany { return $this->hasMany(CustomerOffer::class); }

    public function label(): string
    {
        return $this->discount_type === CustomerOfferDiscountType::Percentage
            ? rtrim(rtrim((string) $this->discount_value, '0'), '.').'% off'
            : '₱'.number_format((float) $this->discount_value, 2).' off';
    }

    protected function casts(): array
    {
        return [
            'discount_type' => CustomerOfferDiscountType::class,
            'discount_value' => 'decimal:2', 'minimum_spend' => 'decimal:2',
            'valid_days' => 'integer', 'max_redemptions' => 'integer', 'is_active' => 'boolean',
        ];
    }
}
