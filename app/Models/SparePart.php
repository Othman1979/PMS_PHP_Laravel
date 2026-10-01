<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'part_number', 'manufacturer', 'unit', 'quantity', 'unit_cost', 'minimum_quantity'])]
class SparePart extends Model
{
    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2', 'quantity' => 'integer', 'minimum_quantity' => 'integer'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->minimum_quantity;
    }
}
