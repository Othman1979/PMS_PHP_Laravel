<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'purchase_request_id', 'received_at', 'received_by_id', 'supplier', 'invoice_number', 'invoice_date', 'attachment_url', 'notes'])]
class GoodsReceipt extends Model
{
    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'invoice_date' => 'date'];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function total(): float
    {
        return (float) $this->items->sum(fn (GoodsReceiptItem $i) => $i->quantity * (float) $i->unit_price);
    }
}
