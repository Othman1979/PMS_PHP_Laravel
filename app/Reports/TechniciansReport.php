<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
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
            Column::int('assigned', __('AssignedRequests')),
            Column::int('completed', __('CompletedRequests')),
            Column::int('lead', __('WorkedAsLead')),
            Column::int('participant', __('WorkedAsParticipant')),
            Column::int('open', __('OpenRequests')),
            Column::percent('completion_rate', __('CompletionRate')),
            Column::hours('avg_response', __('AvgResponseHours')),
            Column::hours('avg_completion', __('AvgCompletionHours')),
            Column::money('cost', __('CostManagedAsLead')),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $closed = RequestStatus::closed();

        $requests = $filters->requests()
            ->whereNotNull('assigned_technician_id')
            ->with(['assignedTechnician', 'teamMembers.user'])->get();

        return User::whereIn('id', $requests->flatMap(fn (MaintenanceRequest $r) => [$r->assigned_technician_id, ...$r->teamMembers->pluck('user_id')->all()])->unique())
            ->when($filters->technicianId, fn ($query) => $query->whereKey($filters->technicianId))
            ->get()->map(function (User $technician) use ($requests, $closed) {
                $group = $requests->filter(fn (MaintenanceRequest $r) => $r->assigned_technician_id === $technician->id || $r->teamMembers->contains('user_id', $technician->id));
                $completed = $group->filter(fn (MaintenanceRequest $r) => in_array($technician->id, $r->actualTechnicianIds(), true));
                $lead = $completed->filter(fn (MaintenanceRequest $r) => $r->teamMembers->firstWhere('user_id', $technician->id)?->worked_as_lead
                    ?? $r->assigned_technician_id === $technician->id);

                return [
                    'technician' => $technician?->full_name ?? '-',
                    'specialty' => $technician?->specialtyLabel() ?: '-',
                    'assigned' => $group->count(),
                    'completed' => $completed->count(),
                    'lead' => $lead->count(),
                    'participant' => $completed->count() - $lead->count(),
                    'open' => $group->filter(fn (MaintenanceRequest $r) => in_array($technician->id, $r->technicianIds(), true) && ! in_array($r->status, $closed, true))->count(),
                    'completion_rate' => round($completed->count() / $group->count() * 100, 1),
                    'avg_response' => $this->avgHours($group, 'started_at'),
                    'avg_completion' => $this->avgHours($completed, 'completed_at'),
                    'cost' => round($group->where('assigned_technician_id', $technician->id)->sum(fn (MaintenanceRequest $r) => $this->cost($r)), 2),
                ];
            })
            ->sortByDesc('completed')
            ->values();
    }
}
