<?php

namespace App\Models;

use App\Enums\PmStrategy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'equipment_id', 'strategy', 'frequency_days', 'task_description_en', 'task_description_ar',
    'checklist_id', 'last_executed_date', 'next_due_date', 'is_active',
])]
class PreventiveMaintenancePlan extends Model
{
    protected function casts(): array
    {
        return [
            'strategy' => PmStrategy::class,
            'last_executed_date' => 'date',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function getLocalizedTaskAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->task_description_ar : $this->task_description_en;
    }

    public function isOverdue(): bool
    {
        return $this->is_active && $this->next_due_date->lt(today());
    }

    public function dueSoon(int $days = 7): bool
    {
        return $this->is_active && ! $this->isOverdue() && $this->next_due_date->lte(today()->addDays($days));
    }
}
