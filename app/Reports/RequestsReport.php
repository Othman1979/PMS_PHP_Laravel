<?php

namespace App\Reports;

use App\Models\MaintenanceRequest;
use Illuminate\Support\Collection;

class RequestsReport extends Report
{
    public function key(): string
    {
        return 'requests';
    }

    public function title(): string
    {
        return __('Report_Requests');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'equipment_id', 'technician_id', 'fault_type_id', 'fault_cause_id', 'state'];
    }

    public function columns(): array
    {
        return [
            Column::link('request_number', __('RequestNumber'), 'url'),
            Column::text('description', __('Description'), width: 260),
            Column::text('equipment', __('Equipment'), groupable: true),
            Column::text('location', __('FaultLocation')),
            Column::text('department', __('Department'), groupable: true),
            Column::text('created_by', __('CreatedBy'), groupable: true, visible: false),
            Column::text('technician', __('LeadTechnician'), groupable: true),
            Column::text('workers', __('ActualParticipants'), width: 280),
            Column::badge('priority', __('Priority'), 'priority_color'),
            Column::text('fault_type', __('FaultType'), groupable: true),
            Column::text('fault_cause', __('FaultCause'), groupable: true),
            Column::badge('status', __('Status'), 'status_class'),
            Column::date('created_at', __('CreatedAt')),
            Column::date('completed_at', __('CompletedAt')),
            Column::hours('hours', __('CompletionHours')),
            Column::money('cost_labor', __('LaborCost')),
            Column::money('cost_parts', __('PartsCost')),
            Column::money('cost', __('Cost')),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        return $filters->requests()
            ->with(['department', 'equipment', 'assignedTechnician', 'createdBy', 'priority', 'faultType', 'faultCause', 'teamMembers.user'])
            ->latest()
            ->get()
            ->map(fn (MaintenanceRequest $r) => [
                'request_number' => $r->request_number,
                'url' => route('requests.show', $r),
                'description' => $r->description,
                'equipment' => $r->equipment?->name ?? __('GeneralFault'),
                'location' => $r->equipment?->location ?? $r->location ?? '-',
                'department' => $r->department?->localized_name ?? '-',
                'created_by' => $r->createdBy?->full_name ?? '-',
                'technician' => $r->assignedTechnician?->full_name ?? '-',
                'workers' => $r->teamMembers->whereNotNull('worked_at')->map(fn ($member) => $member->user->full_name.' ('.($member->worked_as_lead ? __('LeadTechnician') : __('Participant')).
                    ($member->user->specialtyLabel() ? ' — '.$member->user->specialtyLabel() : '').')')->implode(' / ')
                    ?: ($r->teamMembers->isEmpty() && $r->completed_at ? $r->assignedTechnician?->full_name : '-'),
                'priority' => $r->priority->label(),
                'priority_color' => $r->priority->color,
                'fault_type' => $r->faultType?->localized_name ?? '-',
                'fault_cause' => $r->faultCause?->localized_name ?? '-',
                'status' => $r->status->label(),
                'status_class' => $r->status->badge(),
                'created_at' => $r->created_at->format('Y-m-d'),
                'completed_at' => $r->completed_at?->format('Y-m-d'),
                'hours' => $r->completed_at ? round($r->created_at->diffInMinutes($r->completed_at) / 60, 1) : null,
                'cost_labor' => (float) $r->cost_labor,
                'cost_parts' => (float) $r->cost_parts,
                'cost' => $this->cost($r),
            ])
            ->values();
    }
}
