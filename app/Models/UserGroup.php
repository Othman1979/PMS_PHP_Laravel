<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/** A named bundle of permissions; users inherit the union of the groups they belong to. */
#[Fillable(['name_ar', 'name_en', 'description', 'default_for_role'])]
class UserGroup extends Model
{
    protected function casts(): array
    {
        return ['default_for_role' => Role::class];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserGroupPermission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function getLocalizedNameAttribute(): string
    {
        $name = app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;

        return $name ?: ($this->name_en ?: $this->name_ar);
    }

    /** @return list<string> */
    public function permissionValues(): array
    {
        return $this->permissions->pluck('permission')->all();
    }

    /** @param  list<string>  $permissions */
    public function syncPermissions(array $permissions): void
    {
        $valid = array_values(array_intersect(array_unique($permissions), Permission::values()));
        $this->permissions()->delete();
        $this->permissions()->createMany(array_map(fn (string $p) => ['permission' => $p], $valid));
        static::flushCache();
    }

    public static function defaultFor(Role $role): ?self
    {
        return static::query()->where('default_for_role', $role->value)->first();
    }

    /** Creates the built-in group for each role (idempotent) so a fresh install behaves like before groups existed. */
    public static function seedDefaults(): void
    {
        $names = [
            Role::Admin->value => ['الإدارة', 'Administrators'],
            Role::Coordinator->value => ['مشرفو الصيانة', 'Maintenance Coordinators'],
            Role::Technician->value => ['الفنيون', 'Technicians'],
            Role::DepartmentManager->value => ['مدراء الأقسام', 'Department Managers'],
            Role::Employee->value => ['الموظفون', 'Employees'],
            Role::FoodSafety->value => ['سلامة الغذاء', 'Food Safety'],
        ];
        foreach (Role::cases() as $role) {
            if (static::defaultFor($role) !== null) {
                continue;
            }
            [$ar, $en] = $names[$role->value];
            $group = static::create(['name_ar' => $ar, 'name_en' => $en, 'default_for_role' => $role]);
            $group->syncPermissions(array_map(fn (Permission $p) => $p->value, Permission::defaultsFor($role)));
        }
    }

    public static function flushCache(): void
    {
        Cache::increment('user_groups.version');
    }

    public static function cacheVersion(): int
    {
        return (int) Cache::rememberForever('user_groups.version', fn () => 1);
    }
}
