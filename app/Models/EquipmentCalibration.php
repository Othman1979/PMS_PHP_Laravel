<?php

namespace App\Models;

use App\Enums\CalibrationResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One calibration event of a measuring device; the latest one drives the equipment's calibration status. */
#[Fillable(['equipment_id', 'calibrated_at', 'next_due_date', 'result', 'provider', 'certificate_number', 'certificate_url', 'notes', 'recorded_by_id'])]
class EquipmentCalibration extends Model
{
    protected function casts(): array
    {
        return [
            'calibrated_at' => 'date',
            'next_due_date' => 'date',
            'result' => CalibrationResult::class,
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
