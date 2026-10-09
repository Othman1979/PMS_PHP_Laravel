<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['username', 'full_name', 'email', 'phone', 'password', 'role', 'department_id', 'specialty', 'locale', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
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

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'assigned_technician_id');
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

    /** Admin or the food-safety officer: signs equipment release, commissioning and food-safety decisions. */
    /** Sees every request regardless of department, so subscribes to the staff-wide channel. */
    public function canWatchAllRequests(): bool
    {
        return $this->canManage() || $this->isFoodSafety();
    }

    public function canApproveFoodSafety(): bool
    {
        return $this->hasRole(Role::Admin, Role::FoodSafety);
    }

    public function isFoodSafety(): bool
    {
        return $this->role === Role::FoodSafety;
    }

    /** Field technician: only the tasks assigned to them, no menus or browsing. */
    public function isTechnician(): bool
    {
        return $this->role === Role::Technician;
    }

    /** Restaurant staff: only the QR quick-request screen, no menus. */
    public function isEmployee(): bool
    {
        return $this->role === Role::Employee;
    }

    /** Whether the side navigation pane is shown; technicians and employees get a single focused screen. */
    public function hasNavPane(): bool
    {
        return ! $this->isEmployee() && ! $this->isTechnician();
    }

    /** Language the user last chose in the UI; notifications are written in it. */
    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->full_name ?: $this->username, 0, 1));
    }
}
