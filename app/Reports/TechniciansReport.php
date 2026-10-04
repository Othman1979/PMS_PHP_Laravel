<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\MaintenanceRequest;
use Illuminate\Support\Collection;

class TechniciansReport extends Report
{
    public function key(): string
    {
        return 'technicians';
    }

    public function title(): string
    {
        return __('Report_Technicians');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'technician_id', 'fault_type_id', 'state'];
    }

    public function columns(): array
    {
        return [
            Column::text('technician', __('Technician')),
            Column::text('specialty', __('Specialty'), groupable: true),
            Column::int('assigned', __('AssignedRequests'), total: true),
            Column::int('completed', __('CompletedRequests'), total: true),
            Column::int('open', __('OpenRequests'), total: true),
            Column::percent('completion_rate', __('CompletionRate')),
            Column::hours('avg_response', __('AvgResponseHours')),
            Column::hours('avg_completion', __('AvgCompletionHours')),
            Column::money('cost', __('TotalCost')),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $closed = RequestStatus::closed();

        return $filters->requests()
            ->whereNotNull('assigned_technician_id')
            ->with('assignedTechnician')
            ->get()
            ->groupBy('assigned_technician_id')
            ->map(function (Collection $group) use ($closed) {
                $technician = $group->first()->assignedTechnician;
                $completed = $group->filter(fn (MaintenanceRequest $r) => $r->completed_at !== null);

                return [
                    'technician' => $technician?->full_name ?? '-',
                    'specialty' => $technician?->specialty?->label() ?? '-',
                    'assigned' => $group->count(),
                    'completed' => $completed->count(),
                    'open' => $group->reject(fn (MaintenanceRequest $r) => in_array($r->status, $closed, true))->count(),
                    'completion_rate' => round($completed->count() / $group->count() * 100, 1),
                    'avg_response' => $this->avgHours($group, 'started_at'),
                    'avg_completion' => $this->avgHours($group, 'completed_at'),
                    'cost' => round($group->sum(fn (MaintenanceRequest $r) => $this->cost($r)), 2),
                ];
            })
            ->sortByDesc('completed')
            ->values();
    }
}
