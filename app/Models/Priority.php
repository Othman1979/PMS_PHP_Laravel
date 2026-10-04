<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Admin-managed request priority. `code` is a stable key used only by the installer
 * (seeded rows) — admins rename/recolor rows freely; `rank` orders them (higher = more urgent).
 */
#[Fillable(['code', 'name_en', 'name_ar', 'hint_en', 'hint_ar', 'color', 'rank', 'is_default', 'is_critical', 'show_in_quick', 'is_active'])]
class Priority extends Model
{
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_default' => 'boolean',
            'is_critical' => 'boolean',
            'show_in_quick' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('rank')->orderBy('id');
    }

    /** @return Collection<int, Priority> */
    public static function activeOrdered(): Collection
    {
        return static::query()->active()->ordered()->get();
    }

    public static function default(): Priority
    {
        return static::query()->active()->where('is_default', true)->ordered()->first()
            ?? static::query()->active()->ordered()->firstOrFail();
    }

    /** Priority used for auto-generated preventive-maintenance requests: the lowest-ranked active one. */
    public static function forPreventive(): Priority
    {
        return static::query()->active()->where('code', 'Scheduled')->first()
            ?? static::query()->active()->ordered()->firstOrFail();
    }

    /** SQL expression resolving a request's priority rank, for ORDER BY. */
    public static function rankSql(string $column = 'priority_id'): string
    {
        return "(SELECT `rank` FROM priorities WHERE priorities.id = {$column})";
    }

    public function label(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function hint(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->hint_ar : $this->hint_en;
    }

    public function badge(): string
    {
        return 'prio-'.$this->id;
    }

    /** Inline style for badges/dots so admin-chosen colors apply without CSS classes. */
    public function badgeStyle(): string
    {
        return 'background:'.$this->color.';color:'.$this->textColor();
    }

    /** Black or white, whichever reads better on the badge color. */
    public function textColor(): string
    {
        $hex = ltrim($this->color, '#');
        if (strlen($hex) !== 6) {
            return '#fff';
        }
        [$r, $g, $b] = array_map(fn (string $c) => hexdec($c), str_split($hex, 2));

        return ($r * 299 + $g * 587 + $b * 114) / 1000 > 150 ? '#111' : '#fff';
    }
}
