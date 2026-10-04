<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\MaintenanceRequest;
use Illuminate\Support\Collection;

/**
 * Statistical overview: headline figures plus small breakdown tables. Rendered with
 * its own view instead of the grid; exports produce one sheet/table per section.
 */
class SummaryReport extends Report
{
    public function key(): string
    {
        return 'summary';
    }

    public function title(): string
    {
        return __('Report_Summary');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'equipment_id', 'technician_id', 'fault_type_id', 'fault_cause_id', 'state'];
    }

    public function isSummary(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [Column::text('label', __('Metric')), Column::text('value', __('Value'))];
    }

    public function rows(ReportFilters $filters): Collection
    {
        return collect($this->build($filters)['cards'])->map(fn (array $c) => ['label' => $c['label'], 'value' => $c['value']]);
    }

    /**
     * @return array{
     *     cards: list<array{label: string, value: string|int|float, tone: string}>,
     *     sections: list<array{title: string, columns: list<Column>, rows: Collection<int, array<string, mixed>>}>
     * }
     */
    public function build(ReportFilters $filters): array
    {
        $closed = RequestStatus::closed();
        $requests = $filters->requests()->with(['department', 'equipment', 'assignedTechnician', 'priority', 'faultType', 'faultCause'])->get();
        $isClosed = fn (MaintenanceRequest $r) => in_array($r->status, $closed, true);
        $completed = $requests->filter(fn (MaintenanceRequest $r) => $r->completed_at !== null);
        $corrective = $requests->reject(fn (MaintenanceRequest $r) => $r->is_preventive);

        $countTable = fn (Collection $set, callable $key, string $label): array => [
            'title' => $label,
            'columns' => [Column::text('name', $label), Column::int('count', __('Count'), total: true), Column::percent('share', __('ShareOfTotal'))],
            'rows' => $set->countBy($key)->sortDesc()->map(fn (int $count, string $name) => [
                'name' => $name,
                'count' => $count,
                'share' => $set->count() > 0 ? round($count / $set->count() * 100, 1) : 0,
            ])->values(),
        ];

        $costTable = fn (Collection $set, callable $key, string $label): array => [
            'title' => $label,
            'columns' => [Column::text('name', $label), Column::int('count', __('Count'), total: true), Column::money('cost', __('TotalCost'))],
            'rows' => $set->groupBy($key)->map(fn (Collection $g, string $name) => [
                'name' => $name,
                'count' => $g->count(),
                'cost' => round($g->sum(fn (MaintenanceRequest $r) => $this->cost($r)), 2),
            ])->sortByDesc('cost')->values(),
        ];

        return [
            'cards' => [
                ['label' => __('TotalRequests'), 'value' => $requests->count(), 'tone' => ''],
                ['label' => __('OpenRequests'), 'value' => $requests->reject($isClosed)->count(), 'tone' => 'primary'],
                ['label' => __('ClosedRequests'), 'value' => $requests->filter($isClosed)->count(), 'tone' => 'dark'],
                ['label' => __('AvgResponseHours'), 'value' => $this->avgHours($requests, 'started_at') ?? 0, 'tone' => ''],
                ['label' => __('AvgCompletionHours'), 'value' => $this->avgHours($requests, 'completed_at') ?? 0, 'tone' => ''],
                ['label' => __('TotalCost'), 'value' => number_format($requests->sum(fn (MaintenanceRequest $r) => $this->cost($r)), 2), 'tone' => 'success'],
            ],
            'sections' => [
                $countTable($requests, fn (MaintenanceRequest $r) => $r->status->label(), __('RequestsPerStatus')),
                $countTable($requests, fn (MaintenanceRequest $r) => $r->priority->label(), __('RequestsPerPriority')),
                $countTable($requests, fn (MaintenanceRequest $r) => $r->department?->localized_name ?? '-', __('RequestsPerDepartment')),
                $countTable($corrective, fn (MaintenanceRequest $r) => $r->faultType?->localized_name ?? __('NotSpecified'), __('RequestsPerFaultType')),
                $countTable($completed->reject(fn (MaintenanceRequest $r) => $r->is_preventive), fn (MaintenanceRequest $r) => $r->faultCause?->localized_name ?? __('NotSpecified'), __('RequestsPerFaultCause')),
                $countTable($corrective->filter(fn (MaintenanceRequest $r) => $r->equipment !== null), fn (MaintenanceRequest $r) => $r->equipment->name, __('TopFailingEquipment')),
                [
                    'title' => __('TechnicianPerformance'),
                    'columns' => [Column::text('name', __('Technician')), Column::int('count', __('CompletedRequests'), total: true), Column::hours('hours', __('AvgCompletionHours'))],
                    'rows' => $completed->filter(fn (MaintenanceRequest $r) => $r->assignedTechnician !== null)
                        ->groupBy(fn (MaintenanceRequest $r) => $r->assignedTechnician->full_name)
                        ->map(fn (Collection $g, string $name) => ['name' => $name, 'count' => $g->count(), 'hours' => $this->avgHours($g, 'completed_at')])
                        ->sortByDesc('count')->values(),
                ],
                $costTable($requests, fn (MaintenanceRequest $r) => $r->department?->localized_name ?? '-', __('CostPerDepartment')),
                $costTable($requests->filter(fn (MaintenanceRequest $r) => $r->equipment !== null), fn (MaintenanceRequest $r) => $r->equipment->name, __('CostPerEquipment')),
            ],
        ];
    }
}
