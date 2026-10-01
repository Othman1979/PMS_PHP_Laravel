<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'spare_part_id', 'quantity', 'unit_cost_at_use'])]
class RequestPartUsed extends Model
{
    public $timestamps = false;

    protected $table = 'request_parts_used';

    protected function casts(): array
    {
        return ['unit_cost_at_use' => 'decimal:2'];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
