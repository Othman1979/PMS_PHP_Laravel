<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TechnicianTrade;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

#[Fillable(['username', 'full_name', 'email', 'phone', 'password', 'role', 'department_id', 'specialty', 'trade', 'locale', 'is_active', 'is_developer'])]
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
            'trade' => TechnicianTrade::class,
            'is_active' => 'boolean',
            'is_developer' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function specialtyLabel(): string
    {
        return implode(' · ', array_filter([$this->trade?->label(), $this->specialty?->label()]));
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'assigned_technician_id');
    }

    /** Developer accounts stay out of user lists unless the viewer is a developer too. */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $viewer?->isDeveloper() ? $query : $query->where('is_developer', false);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Hidden maintainer account: full admin rights plus the developer-only settings; invisible to other users. */
    public function isDeveloper(): bool
    {
        return (bool) $this->is_developer;
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class)->withTimestamps();
    }

    /** @var Collection<int, string>|null */
    private ?Collection $resolvedPermissions = null;

    private ?int $resolvedPermissionsVersion = null;

    /** Union of the permissions granted by every group the user belongs to. @return Collection<int, string> */
    public function permissions(): Collection
    {
        $version = UserGroup::cacheVersion();
        if ($this->resolvedPermissions === null || $this->resolvedPermissionsVersion !== $version) {
            $this->resolvedPermissions = UserGroupPermission::query()
                ->whereIn('user_group_id', $this->groups()->select('user_groups.id'))
                ->distinct()
                ->pluck('permission');
            $this->resolvedPermissionsVersion = $version;
        }

        return $this->resolvedPermissions;
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return $this->isAdmin() || $this->permissions()->contains($value);
    }

    public function forgetPermissions(): void
    {
        $this->resolvedPermissions = null;
    }

    /** Puts a user into the built-in group of their role when no group was chosen explicitly. */
    public function attachDefaultGroup(): void
    {
        $group = UserGroup::defaultFor($this->role);
        if ($group !== null) {
            $this->groups()->syncWithoutDetaching([$group->id]);
        }
        $this->forgetPermissions();
    }

    /** Maintenance staff who triage and assign requests. */
    public function canManage(): bool
    {
        return $this->hasPermission(Permission::RequestsAssign);
    }

    /** Sees every request regardless of department, so subscribes to the staff-wide channel. */
    public function canWatchAllRequests(): bool
    {
        return $this->hasPermission(Permission::RequestsViewAll);
    }

    /** Signs equipment release, commissioning and food-safety decisions. */
    public function canApproveFoodSafety(): bool
    {
        return $this->hasPermission(Permission::RequestsFoodSafety);
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
