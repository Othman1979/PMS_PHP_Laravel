<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'checklist_item_id', 'is_compliant', 'note'])]
class ChecklistResult extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_compliant' => 'boolean'];
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }
}
