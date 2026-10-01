<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_request_id', 'spare_part_id', 'part_name', 'part_number', 'quantity', 'unit', 'estimated_unit_price', 'notes'])]
class PurchaseRequestItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['estimated_unit_price' => 'decimal:2'];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
