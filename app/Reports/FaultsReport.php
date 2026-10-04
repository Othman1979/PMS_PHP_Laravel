<?php

namespace App\Reports;

use App\Models\MaintenanceRequest;
use Illuminate\Support\Collection;

class FaultsReport extends Report
{
    public function key(): string
    {
        return 'faults';
    }

    public function title(): string
    {
        return __('Report_Faults');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'equipment_id', 'technician_id', 'fault_type_id', 'fault_cause_id', 'state'];
    }

    public function defaultGroup(): ?string
    {
        return 'fault_type';
    }

    public function columns(): array
    {
        return [
            Column::text('fault_type', __('FaultType'), groupable: true),
            Column::text('fault_cause', __('FaultCause'), groupable: true),
            Column::int('requests', __('TotalRequests'), total: true),
            Column::int('completed', __('CompletedRequests'), total: true),
            Column::int('equipment_count', __('AffectedEquipment')),
            Column::hours('avg_completion', __('AvgCompletionHours')),
            Column::money('cost', __('TotalCost')),
            Column::percent('share', __('ShareOfTotal')),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $requests = $filters->requests()->where('is_preventive', false)->with(['faultType', 'faultCause'])->get();
        $total = $requests->count();

        return $requests
            ->groupBy(fn (MaintenanceRequest $r) => ($r->fault_type_id ?? 0).'|'.($r->fault_cause_id ?? 0))
            ->map(function (Collection $group) use ($total) {
                $first = $group->first();

                return [
                    'fault_type' => $first->faultType?->localized_name ?? __('NotSpecified'),
                    'fault_cause' => $first->faultCause?->localized_name ?? __('NotSpecified'),
                    'requests' => $group->count(),
                    'completed' => $group->filter(fn (MaintenanceRequest $r) => $r->completed_at !== null)->count(),
                    'equipment_count' => $group->pluck('equipment_id')->filter()->unique()->count(),
                    'avg_completion' => $this->avgHours($group, 'completed_at'),
                    'cost' => round($group->sum(fn (MaintenanceRequest $r) => $this->cost($r)), 2),
                    'share' => $total > 0 ? round($group->count() / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('requests')
            ->values();
    }
}
