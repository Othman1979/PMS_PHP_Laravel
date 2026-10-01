<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_en', 'name_ar', 'category'])]
class Checklist extends Model
{
    protected function casts(): array
    {
        return ['category' => EquipmentCategory::class];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(PreventiveMaintenancePlan::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
