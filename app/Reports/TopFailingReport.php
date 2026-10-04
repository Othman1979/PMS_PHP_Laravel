<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\MaintenanceRequest;
use Illuminate\Support\Collection;

class TopFailingReport extends Report
{
    public function key(): string
    {
        return 'top-failing';
    }

    public function title(): string
    {
        return __('Report_TopFailing');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'fault_type_id', 'fault_cause_id'];
    }

    public function columns(): array
    {
        return [
            Column::link('code', __('Code'), 'url'),
            Column::text('equipment', __('Equipment')),
            Column::text('department', __('Department'), groupable: true),
            Column::text('category', __('Category'), groupable: true),
            Column::int('failures', __('FailureCount'), total: true),
            Column::int('open', __('OpenRequests'), total: true),
            Column::date('last_failure', __('LastFailure')),
            Column::hours('downtime', __('DowntimeHours')),
            Column::money('cost', __('TotalCost')),
            Column::badge('status', __('EquipmentStatus'), 'status_class'),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $closed = RequestStatus::closed();

        return $filters->requests()
            ->where('is_preventive', false)
            ->whereNotNull('equipment_id')
            ->with(['equipment.department'])
            ->get()
            ->groupBy('equipment_id')
            ->map(function (Collection $group) use ($closed) {
                $equipment = $group->first()->equipment;
                $completed = $group->filter(fn (MaintenanceRequest $r) => $r->completed_at !== null);

                return [
                    'code' => $equipment->code,
                    'url' => route('equipment.show', $equipment),
                    'equipment' => $equipment->name,
                    'department' => $equipment->department?->localized_name ?? '-',
                    'category' => $equipment->category->label(),
                    'failures' => $group->count(),
                    'open' => $group->reject(fn (MaintenanceRequest $r) => in_array($r->status, $closed, true))->count(),
                    'last_failure' => $group->max('created_at')?->format('Y-m-d'),
                    'downtime' => round($completed->sum(fn (MaintenanceRequest $r) => $r->created_at->diffInMinutes($r->completed_at)) / 60, 1),
                    'cost' => round($group->sum(fn (MaintenanceRequest $r) => $this->cost($r)), 2),
                    'status' => $equipment->status->label(),
                    'status_class' => $equipment->status->badge(),
                ];
            })
            ->sortByDesc('failures')
            ->values();
    }
}
