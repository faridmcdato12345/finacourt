<?php

namespace App\Models;

use App\Enums\OwnerPayoutMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'method',
    'account_name',
    'details',
    'is_active',
    'updated_by_user_id',
])]
#[Hidden(['details'])]
class OwnerPayoutProfile extends Model
{
    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** @return array<string, mixed> */
    public function destinationSnapshot(): array
    {
        return [
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'account_name' => $this->account_name,
            'details' => $this->details,
        ];
    }

    protected function casts(): array
    {
        return [
            'method' => OwnerPayoutMethod::class,
            'details' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }
}
