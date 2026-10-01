<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'created_by_id', 'reason', 'status', 'decision_by_id', 'decision_at', 'decision_note'])]
class PurchaseRequest extends Model
{
    protected function casts(): array
    {
        return ['status' => PurchaseRequestStatus::class, 'decision_at' => 'datetime'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function estimatedTotal(): float
    {
        return (float) $this->items->sum(fn (PurchaseRequestItem $i) => $i->quantity * (float) $i->estimated_unit_price);
    }
}
