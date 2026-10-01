<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['username', 'full_name', 'email', 'phone', 'password', 'role', 'department_id', 'specialty', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'specialty' => EquipmentCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Admin or Coordinator — the maintenance staff who triage and manage. */
    public function canManage(): bool
    {
        return $this->hasRole(Role::Admin, Role::Coordinator);
    }

    public function isTechnician(): bool
    {
        return $this->role === Role::Technician;
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->full_name ?: $this->username, 0, 1));
    }
}
