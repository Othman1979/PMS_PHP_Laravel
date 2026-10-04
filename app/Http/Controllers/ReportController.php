<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = $this->date($request->query('from')) ?? CarbonImmutable::today()->subMonth();
        $to = $this->date($request->query('to')) ?? CarbonImmutable::today();
        $state = in_array($request->query('state'), ['open', 'closed'], true) ? $request->query('state') : '';
        $closed = RequestStatus::closed();

        $requests = MaintenanceRequest::with(['department', 'equipment', 'assignedTechnician', 'createdBy', 'priority', 'faultType', 'faultCause'])
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($request->filled('equipment_id'), fn ($q) => $q->where('equipment_id', $request->integer('equipment_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('technician_id'), fn ($q) => $q->where('assigned_technician_id', $request->integer('technician_id')))
            ->when($request->filled('fault_type_id'), fn ($q) => $q->where('fault_type_id', $request->integer('fault_type_id')))
            ->when($request->filled('fault_cause_id'), fn ($q) => $q->where('fault_cause_id', $request->integer('fault_cause_id')))
            ->when($state === 'open', fn ($q) => $q->whereNotIn('status', $closed))
            ->when($state === 'closed', fn ($q) => $q->whereIn('status', $closed))
            ->latest()
            ->get();

        $isClosed = fn (MaintenanceRequest $r) => in_array($r->status, $closed, true);
        $cost = fn (MaintenanceRequest $r) => (float) $r->cost_labor + (float) $r->cost_parts;
        $completed = $requests->filter(fn (MaintenanceRequest $r) => $r->completed_at !== null);
        $started = $requests->filter(fn (MaintenanceRequest $r) => $r->started_at !== null);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'state' => $state,
            'requests' => $requests,
            'total' => $requests->count(),
            'openCount' => $requests->reject($isClosed)->count(),
            'closedCount' => $requests->filter($isClosed)->count(),
            'totalCost' => $requests->sum($cost),
            'avgResponseHours' => round((float) $started->avg(fn (MaintenanceRequest $r) => $r->created_at->diffInMinutes($r->started_at) / 60), 1),
            'avgCompletionHours' => round((float) $completed->avg(fn (MaintenanceRequest $r) => $r->created_at->diffInMinutes($r->completed_at) / 60), 1),
            'perDepartment' => $this->countBy($requests, fn (MaintenanceRequest $r) => $r->department?->localized_name ?? '-'),
            'perEquipment' => $this->countBy($requests, fn (MaintenanceRequest $r) => $r->equipment?->name ?? '-'),
            'perStatus' => $requests->countBy(fn (MaintenanceRequest $r) => $r->status->value),
            'perPriority' => $this->countBy($requests, fn (MaintenanceRequest $r) => $r->priority->label()),
            'perFaultType' => $this->countBy($requests->filter(fn (MaintenanceRequest $r) => ! $r->is_preventive), fn (MaintenanceRequest $r) => $r->faultType?->localized_name ?? __('NotSpecified')),
            'perFaultCause' => $this->countBy($completed->filter(fn (MaintenanceRequest $r) => ! $r->is_preventive), fn (MaintenanceRequest $r) => $r->faultCause?->localized_name ?? __('NotSpecified')),
            'technicianStats' => $completed->filter(fn (MaintenanceRequest $r) => $r->assignedTechnician !== null)
                ->groupBy(fn (MaintenanceRequest $r) => $r->assignedTechnician->full_name)
                ->map(fn (Collection $g) => [
                    'count' => $g->count(),
                    'hours' => round((float) $g->avg(fn (MaintenanceRequest $r) => $r->created_at->diffInMinutes($r->completed_at) / 60), 1),
                ])
                ->sortByDesc('count'),
            'topFailing' => $requests->filter(fn (MaintenanceRequest $r) => $r->equipment !== null && ! $r->is_preventive)
                ->groupBy('equipment_id')
                ->map(fn (Collection $g) => ['equipment' => $g->first()->equipment, 'count' => $g->count()])
                ->sortByDesc('count')->take(10),
            'costPerDepartment' => $completed->groupBy(fn (MaintenanceRequest $r) => $r->department?->localized_name ?? '-')
                ->map(fn (Collection $g) => $g->sum($cost))->sortDesc(),
            'costPerEquipment' => $completed->filter(fn (MaintenanceRequest $r) => $r->equipment !== null)
                ->groupBy(fn (MaintenanceRequest $r) => $r->equipment->name)
                ->map(fn (Collection $g) => $g->sum($cost))->sortDesc(),
            'equipmentList' => Equipment::query()->orderBy('code')->get(['id', 'code', 'name']),
            'departments' => Department::active()->ordered()->get(),
            'technicians' => User::query()->where('role', Role::Technician)->where('is_active', true)->orderBy('full_name')->get(['id', 'full_name']),
            'faultTypes' => FaultType::query()->ordered()->get(),
            'faultCauses' => FaultCause::query()->ordered()->get(),
        ]);
    }

    /** @return Collection<string, int> */
    private function countBy(Collection $requests, callable $key): Collection
    {
        return $requests->countBy($key)->sortDesc();
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
