<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\OrganizationPermission;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'user_id',
    'role',
    'permissions',
    'joined_at',
    'suspended_at',
    'suspended_by_user_id',
    'removed_at',
    'removed_by_user_id',
])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasPermission(OrganizationPermission $permission): bool
    {
        return $this->isActive()
            && ($this->role === MembershipRole::Owner
                || in_array($permission->value, $this->permissions ?? [], true));
    }

    public function isActive(): bool
    {
        return $this->suspended_at === null && $this->removed_at === null;
    }

    /** @param Builder<Membership> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('suspended_at')->whereNull('removed_at');
    }

    /** @return BelongsTo<User, $this> */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'permissions' => 'array',
            'joined_at' => 'datetime',
            'suspended_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }
}
