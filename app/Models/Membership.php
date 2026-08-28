<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\OrganizationPermission;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'role', 'permissions', 'joined_at'])]
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
        return $this->role === MembershipRole::Owner
            || in_array($permission->value, $this->permissions ?? [], true);
    }

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'permissions' => 'array',
            'joined_at' => 'datetime',
        ];
    }
}
