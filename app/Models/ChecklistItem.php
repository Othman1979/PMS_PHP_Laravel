<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['checklist_id', 'text_en', 'text_ar', 'sort_order'])]
class ChecklistItem extends Model
{
    public $timestamps = false;

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function getLocalizedTextAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->text_ar : $this->text_en;
    }
}
