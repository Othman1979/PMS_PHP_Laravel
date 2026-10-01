<?php

namespace App\Services;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Equipment;
use App\Models\PreventiveMaintenancePlan;

/** Converts due preventive maintenance plans into maintenance requests (run by the scheduler or manually). */
class PmGenerator
{
    public function __construct(private RequestWorkflow $workflow) {}

    public function generateDue(): int
    {
        $count = 0;
        $plans = PreventiveMaintenancePlan::with('equipment')
            ->where('is_active', true)
            ->whereDate('next_due_date', '<=', today())
            ->get();

        foreach ($plans as $plan) {
            if ($plan->equipment === null) {
                continue;
            }
            $alreadyOpen = $plan->requests()->whereNotIn('status', [RequestStatus::Closed, RequestStatus::Cancelled])->exists();
            if ($alreadyOpen) {
                continue;
            }

            $this->workflow->create(
                null,
                $plan->equipment,
                $plan->equipment->department_id,
                "[PM] {$plan->task_description_en} / {$plan->task_description_ar}",
                RequestPriority::Scheduled,
                preventive: true,
                planId: $plan->id,
            );

            $plan->update(['next_due_date' => today()->addDays($plan->frequency_days)]);
            $plan->equipment->update(['next_maintenance_date' => $plan->next_due_date]);
            $count++;
        }

        return $count;
    }

    public function syncEquipmentNextDate(int $equipmentId): void
    {
        $next = PreventiveMaintenancePlan::query()
            ->where('equipment_id', $equipmentId)->where('is_active', true)
            ->min('next_due_date');

        Equipment::query()->whereKey($equipmentId)->update(['next_maintenance_date' => $next]);
    }
}
