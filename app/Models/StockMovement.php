<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['spare_part_id', 'date', 'type', 'quantity', 'balance_after', 'unit_cost', 'goods_receipt_id', 'maintenance_request_id', 'user_id', 'note'])]
class StockMovement extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'date' => 'datetime', 'unit_cost' => 'decimal:2'];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
