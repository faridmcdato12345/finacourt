<?php

namespace App\Models;

use App\Enums\CommissionCalculation;
use App\Enums\CommissionRuleTrigger;
use Database\Factories\CommissionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'trigger', 'calculation', 'fixed_amount', 'currency', 'is_active',
    'effective_from', 'effective_until', 'created_by_user_id',
])]
class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory;

    public function isEffectiveAt(\DateTimeInterface $at): bool
    {
        return $this->is_active
            && ($this->effective_from === null || $this->effective_from->startOfDay()->lte($at))
            && ($this->effective_until === null || $this->effective_until->endOfDay()->gte($at));
    }

    protected function casts(): array
    {
        return [
            'trigger' => CommissionRuleTrigger::class,
            'calculation' => CommissionCalculation::class,
            'fixed_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'immutable_date',
            'effective_until' => 'immutable_date',
        ];
    }
}
