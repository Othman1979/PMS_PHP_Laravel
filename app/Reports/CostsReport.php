<?php

namespace App\Reports;

use App\Models\MaintenanceRequest;
use Illuminate\Support\Collection;

class CostsReport extends Report
{
    public function key(): string
    {
        return 'costs';
    }

    public function title(): string
    {
        return __('Report_Costs');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'technician_id', 'fault_type_id', 'state'];
    }

    public function columns(): array
    {
        return [
            Column::text('department', __('Department')),
            Column::int('requests', __('TotalRequests'), total: true),
            Column::int('completed', __('CompletedRequests'), total: true),
            Column::int('preventive', __('PreventiveRequests'), total: true),
            Column::money('cost_labor', __('LaborCost')),
            Column::money('cost_parts', __('PartsCost')),
            Column::money('cost', __('TotalCost')),
            Column::money('avg_cost', __('AvgCostPerRequest'), total: false),
            Column::percent('share', __('ShareOfTotal')),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $requests = $filters->requests()->with('department')->get();
        $grandTotal = $requests->sum(fn (MaintenanceRequest $r) => $this->cost($r));

        return $requests
            ->groupBy(fn (MaintenanceRequest $r) => $r->department_id ?? 0)
            ->map(function (Collection $group) use ($grandTotal) {
                $cost = $group->sum(fn (MaintenanceRequest $r) => $this->cost($r));

                return [
                    'department' => $group->first()->department?->localized_name ?? '-',
                    'requests' => $group->count(),
                    'completed' => $group->filter(fn (MaintenanceRequest $r) => $r->completed_at !== null)->count(),
                    'preventive' => $group->filter(fn (MaintenanceRequest $r) => $r->is_preventive)->count(),
                    'cost_labor' => round($group->sum(fn (MaintenanceRequest $r) => (float) $r->cost_labor), 2),
                    'cost_parts' => round($group->sum(fn (MaintenanceRequest $r) => (float) $r->cost_parts), 2),
                    'cost' => round($cost, 2),
                    'avg_cost' => round($cost / $group->count(), 2),
                    'share' => $grandTotal > 0 ? round($cost / $grandTotal * 100, 1) : 0,
                ];
            })
            ->sortByDesc('cost')
            ->values();
    }
}
