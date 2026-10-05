<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\Equipment;
use App\Models\PreventiveMaintenancePlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * HACCP / FSSC evidence per food-safety-relevant equipment: classification, PM coverage, calibration, commissioning, open faults.
 */
class FoodSafetyReport extends Report
{
    public function key(): string
    {
        return 'food-safety';
    }

    public function title(): string
    {
        return __('Report_FoodSafety');
    }

    public function filterKeys(): array
    {
        return ['department_id'];
    }

    public function defaultFrom(): null
    {
        return null;
    }

    public function defaultGroup(): ?string
    {
        return 'department';
    }

    public function columns(): array
    {
        return [
            Column::link('code', __('Code'), 'url'),
            Column::text('name', __('Name')),
            Column::text('department', __('Department'), groupable: true),
            Column::badge('classification', __('Classification'), 'classification_class'),
            Column::text('ccp', __('CcpReference'), groupable: true),
            Column::badge('pm', __('PMCoverage'), 'pm_class', groupable: true),
            Column::date('next_pm', __('NextPM')),
            Column::badge('calibration', __('Calibration'), 'calibration_class', groupable: true),
            Column::date('next_calibration', __('NextCalibration')),
            Column::badge('commissioning', __('Commissioning'), 'commissioning_class', groupable: true),
            Column::int('open_food_safety_faults', __('OpenFoodSafetyFaults'), total: true),
            Column::int('temporary_repairs', __('OpenTemporaryRepairs'), total: true),
            Column::badge('status', __('Status'), 'status_class', groupable: true),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        return Equipment::query()
            ->foodSafetyRelevant()
            ->with(['department', 'pmPlans' => fn ($q) => $q->where('is_active', true)->orderBy('next_due_date')])
            ->withCount([
                'maintenanceRequests as open_food_safety_faults' => fn (Builder $q) => $q->whereNotIn('status', RequestStatus::closedValues())->where('food_safety_impact', true),
                'maintenanceRequests as temporary_repairs' => fn (Builder $q) => $q->where('is_temporary_repair', true)->whereHas('followUps', fn (Builder $f) => $f->whereNotIn('status', RequestStatus::closedValues())),
            ])
            ->when($filters->departmentId, fn (Builder $q) => $q->where('department_id', $filters->departmentId))
            ->orderBy('code')
            ->get()
            ->map(function (Equipment $e) {
                $plan = $e->pmPlans->first();
                $calibration = $e->calibrationStatus();
                $classification = array_filter([
                    $e->food_contact ? __('FoodContactShort') : null,
                    $e->is_critical ? __('CriticalShort') : null,
                    filled($e->ccp_reference) ? 'CCP/oPRP' : null,
                ]);

                return [
                    'code' => $e->code,
                    'url' => route('equipment.show', $e),
                    'name' => $e->name,
                    'department' => $e->department?->localized_name ?? '-',
                    'classification' => implode(' + ', $classification),
                    'classification_class' => $e->is_critical || filled($e->ccp_reference) ? 'bg-danger' : 'bg-primary',
                    'ccp' => $e->ccp_reference ?? '-',
                    'pm' => $plan instanceof PreventiveMaintenancePlan ? ($plan->isOverdue() ? __('PMOverdue') : __('PMCovered')) : __('PMMissing'),
                    'pm_class' => $plan instanceof PreventiveMaintenancePlan ? ($plan->isOverdue() ? 'bg-warning text-dark' : 'bg-success') : 'bg-danger',
                    'next_pm' => $plan?->next_due_date?->format('Y-m-d'),
                    'calibration' => $calibration->label(),
                    'calibration_class' => $calibration->badge(),
                    'next_calibration' => $e->next_calibration_date?->format('Y-m-d'),
                    'commissioning' => $e->commissioned_at !== null ? __('Commissioned') : __('AwaitingCommissioning'),
                    'commissioning_class' => $e->commissioned_at !== null ? 'bg-success' : 'bg-warning text-dark',
                    'open_food_safety_faults' => (int) $e->open_food_safety_faults,
                    'temporary_repairs' => (int) $e->temporary_repairs,
                    'status' => $e->status->label(),
                    'status_class' => $e->status->badge(),
                ];
            })
            ->values();
    }
}
