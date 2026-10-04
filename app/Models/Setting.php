<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Key/value application settings editable by the admin (auto-assign, escalation, backups). */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const AUTO_ASSIGN = 'auto_assign';

    public const ESCALATE_OVERDUE = 'escalate_overdue';

    public const BACKUP_KEEP = 'backup_keep';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::all()[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : in_array($value, ['1', 'true', 'on'], true);
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    /** @return array<string, string|null> */
    public static function all($columns = ['*']): array
    {
        return Cache::remember('settings.all', 300, fn () => static::query()->pluck('value', 'key')->all());
    }
}
