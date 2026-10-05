<?php

namespace App\Models;

use App\Enums\CalibrationStatus;
use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'category', 'department_id', 'location', 'manufacturer', 'model', 'serial_number',
    'purchase_date', 'vendor', 'purchase_price', 'has_warranty', 'warranty_start', 'warranty_end',
    'warranty_provider', 'warranty_number', 'warranty_document_url', 'status', 'last_maintenance_date',
    'next_maintenance_date', 'manual_file_url', 'photo_url', 'notes',
    'food_contact', 'is_critical', 'ccp_reference', 'hygienic_design', 'is_measuring_device', 'calibration_interval_days',
    'last_calibration_date', 'next_calibration_date', 'calibration_provider', 'calibration_certificate_url',
    'purchase_spec_url', 'conformity_doc_url', 'commissioned_at', 'commissioned_by_id', 'commissioning_notes',
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
            'food_contact' => 'boolean',
            'is_critical' => 'boolean',
            'hygienic_design' => 'boolean',
            'is_measuring_device' => 'boolean',
            'calibration_interval_days' => 'integer',
            'last_calibration_date' => 'date',
            'next_calibration_date' => 'date',
            'commissioned_at' => 'datetime',
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

    public function calibrations(): HasMany
    {
        return $this->hasMany(EquipmentCalibration::class);
    }

    public function commissionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioned_by_id');
    }

    /** Food-contact, CCP/oPRP or critical equipment: the HACCP-relevant set that gets the stricter rules. */
    public function isFoodSafetyRelevant(): bool
    {
        return $this->food_contact || $this->is_critical || filled($this->ccp_reference);
    }

    /** CCP/oPRP or critical equipment: every fault is treated as a food-safety fault without asking the reporter. */
    public function autoEscalatesFoodSafety(): bool
    {
        return $this->is_critical || filled($this->ccp_reference);
    }

    /** New or modified food-safety-relevant equipment needs a trial run and sign-off before it may be "Working". */
    public function requiresCommissioning(): bool
    {
        return $this->isFoodSafetyRelevant() && $this->commissioned_at === null;
    }

    public function hasCommissioningDocuments(): bool
    {
        return $this->purchase_spec_url !== null && $this->conformity_doc_url !== null;
    }

    public function calibrationStatus(int $dueSoonDays = 30): CalibrationStatus
    {
        if (! $this->is_measuring_device) {
            return CalibrationStatus::NotRequired;
        }
        if ($this->next_calibration_date === null) {
            return CalibrationStatus::Missing;
        }
        if ($this->next_calibration_date->isPast() && ! $this->next_calibration_date->isToday()) {
            return CalibrationStatus::Expired;
        }

        return $this->next_calibration_date->lte(today()->addDays($dueSoonDays)) ? CalibrationStatus::DueSoon : CalibrationStatus::Valid;
    }

    public function isCalibrationExpired(): bool
    {
        return $this->calibrationStatus() === CalibrationStatus::Expired;
    }

    #[Scope]
    protected function measuringDevices(Builder $query): void
    {
        $query->where('is_measuring_device', true);
    }

    #[Scope]
    protected function foodSafetyRelevant(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->where('food_contact', true)->orWhere('is_critical', true)->orWhereNotNull('ccp_reference'));
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
