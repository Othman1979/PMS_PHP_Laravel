<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['goods_receipt_id', 'spare_part_id', 'part_name', 'part_number', 'manufacturer', 'unit', 'quantity', 'unit_price', 'notes'])]
class GoodsReceiptItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
