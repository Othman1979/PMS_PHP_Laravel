<?php

namespace App\Services;

use App\Enums\CalibrationStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\User;

/**
 * Daily calibration watch: pushes one notification per measuring device whose calibration is missing, due soon or expired.
 */
class CalibrationReminder
{
    public function __construct(private readonly WebPushService $push) {}

    public function send(int $dueSoonDays = 30): int
    {
        $recipients = User::query()
            ->whereIn('role', [Role::Admin, Role::Coordinator, Role::FoodSafety])
            ->where('is_active', true)
            ->pluck('id')->all();

        $devices = Equipment::query()->measuringDevices()->get()
            ->filter(fn (Equipment $e) => $e->calibrationStatus($dueSoonDays)->needsAttention());

        foreach ($devices as $equipment) {
            $status = $equipment->calibrationStatus($dueSoonDays);
            $this->push->sendLocalized($recipients, function () use ($equipment, $status): array {
                $title = __($status === CalibrationStatus::Expired ? 'Push_CalibrationExpiredTitle' : 'Push_CalibrationDueTitle');
                $due = $equipment->next_calibration_date?->format('Y-m-d') ?? __('Calibration_Missing');

                return [$title, "{$equipment->code} - {$equipment->name} — {$due}"];
            }, route('equipment.show', $equipment, false), 'calibration-'.$equipment->id);

            ActivityLog::record('calibration_reminder', $equipment, $equipment->code.' — '.$status->value, null);
        }

        return $devices->count();
    }
}
