<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'category', 'department_id', 'location', 'manufacturer', 'model', 'serial_number',
    'purchase_date', 'vendor', 'purchase_price', 'has_warranty', 'warranty_start', 'warranty_end',
    'warranty_provider', 'warranty_number', 'warranty_document_url', 'status', 'last_maintenance_date',
    'next_maintenance_date', 'manual_file_url', 'photo_url', 'notes',
])]
class Equipment extends Model
{
    protected $table = 'equipment';

    protected function casts(): array
    {
        return [
            'category' => EquipmentCategory::class,
            'status' => EquipmentStatus::class,
            'purchase_date' => 'date',
            'warranty_start' => 'date',
            'warranty_end' => 'date',
            'last_maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'purchase_price' => 'decimal:2',
            'has_warranty' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function pmPlans(): HasMany
    {
        return $this->hasMany(PreventiveMaintenancePlan::class);
    }

    public function isUnderWarranty(): bool
    {
        return $this->has_warranty && $this->warranty_end !== null && $this->warranty_end->endOfDay()->isFuture();
    }

    public function warrantyExpiresWithin(int $days): bool
    {
        return $this->isUnderWarranty() && $this->warranty_end->lte(today()->addDays($days));
    }
}
