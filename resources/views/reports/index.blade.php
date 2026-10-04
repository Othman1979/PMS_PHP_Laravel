<x-layouts.app :title="__('Reports')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('Reports') }}</h2>
        <button class="btn btn-outline-secondary" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <form method="get" class="card card-body mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('From') }}</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('To') }}</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('Equipment') }}</label>
                <select name="equipment_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($equipmentList as $e)
                        <option value="{{ $e->id }}" @selected(request('equipment_id') == $e->id)>{{ $e->code }} — {{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('Department') }}</label>
                <select name="department_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->localized_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('Technician') }}</label>
                <select name="technician_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($technicians as $t)
                        <option value="{{ $t->id }}" @selected(request('technician_id') == $t->id)>{{ $t->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('FaultType') }}</label>
                <select name="fault_type_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($faultTypes as $t)
                        <option value="{{ $t->id }}" @selected(request('fault_type_id') == $t->id)>{{ $t->localized_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('FaultCause') }}</label>
                <select name="fault_cause_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($faultCauses as $c)
                        <option value="{{ $c->id }}" @selected(request('fault_cause_id') == $c->id)>{{ $c->localized_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1">{{ __('RequestState') }}</label>
                <select name="state" class="form-select">
                    <option value="">—</option>
                    <option value="open" @selected($state === 'open')>{{ __('Open') }}</option>
                    <option value="closed" @selected($state === 'closed')>{{ __('ClosedRequests') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-primary">{{ __('RunReport') }}</button>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach ([
            ['TotalRequests', $total, ''],
            ['OpenRequests', $openCount, 'border-primary'],
            ['ClosedRequests', $closedCount, 'border-dark'],
            ['AvgResponseHours', $avgResponseHours, ''],
            ['AvgCompletionHours', $avgCompletionHours, ''],
            ['TotalCost', number_format($totalCost), 'border-success'],
        ] as [$label, $value, $border])
            <div class="col-6 col-md-2">
                <div class="card text-center {{ $border }}"><div class="card-body py-3">
                    <div class="fs-2 fw-semibold">{{ $value }}</div><div class="small text-muted">{{ __($label) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        @foreach (['RequestsPerDepartment' => $perDepartment, 'RequestsPerEquipment' => $perEquipment, 'RequestsPerPriority' => $perPriority, 'RequestsPerFaultType' => $perFaultType, 'RequestsPerFaultCause' => $perFaultCause] as $title => $rows)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __($title) }}</strong></div>
                    <table class="table table-sm mb-0">
                        @forelse ($rows as $name => $count)
                            <tr><td>{{ $name }}</td><td class="text-end">{{ $count }}</td></tr>
                        @empty
                            <tr><td class="text-muted">{{ __('NoData') }}</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        @endforeach
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header"><strong>{{ __('RequestsPerStatus') }}</strong></div>
                <table class="table table-sm mb-0">
                    @forelse ($perStatus as $status => $count)
                        <tr><td><x-status-badge :status="\App\Enums\RequestStatus::from($status)" /></td><td class="text-end">{{ $count }}</td></tr>
                    @empty
                        <tr><td class="text-muted">{{ __('NoData') }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>{{ __('TechnicianPerformance') }}</strong></div>
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Technician') }}</th><th>{{ __('Count') }}</th><th>{{ __('AvgCompletionHours') }}</th></tr></thead>
                    @foreach ($technicianStats as $name => $s)
                        <tr><td>{{ $name }}</td><td>{{ $s['count'] }}</td><td>{{ $s['hours'] }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>{{ __('TopFailingEquipment') }}</strong></div>
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Equipment') }}</th><th class="text-end">{{ __('FailureCount') }}</th></tr></thead>
                    @foreach ($topFailing as $x)
                        <tr>
                            <td><a href="{{ route('equipment.show', $x['equipment']) }}">{{ $x['equipment']->name }}</a></td>
                            <td class="text-end"><span class="badge bg-danger">{{ $x['count'] }}</span></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        @foreach (['CostPerDepartment' => $costPerDepartment, 'CostPerEquipment' => $costPerEquipment] as $title => $rows)
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><strong>{{ __($title) }}</strong></div>
                    <table class="table table-sm mb-0">
                        @foreach ($rows as $name => $c)
                            <tr><td>{{ $name }}</td><td class="text-end">{{ number_format($c, 2) }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mt-4">
        <div class="card-header"><strong>{{ __('RequestsList') }}</strong> ({{ $total }})</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('RequestNumber') }}</th><th>{{ __('Description') }}</th><th>{{ __('Equipment') }}</th>
                        <th>{{ __('Department') }}</th><th>{{ __('CreatedBy') }}</th><th>{{ __('Technician') }}</th>
                        <th>{{ __('Priority') }}</th><th>{{ __('FaultType') }}</th><th>{{ __('FaultCause') }}</th><th>{{ __('Status') }}</th><th>{{ __('CreatedAt') }}</th>
                        <th>{{ __('CompletedAt') }}</th><th>{{ __('Cost') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr>
                            <td><a href="{{ route('requests.show', $r) }}">{{ $r->request_number }}</a></td>
                            <td class="text-truncate" style="max-width:200px">{{ $r->description }}</td>
                            <td>{{ $r->equipment?->name ?? '-' }}</td>
                            <td>{{ $r->department?->localized_name }}</td>
                            <td>{{ $r->createdBy?->full_name }}</td>
                            <td>{{ $r->assignedTechnician?->full_name ?? '-' }}</td>
                            <td><x-status-badge :status="$r->priority" /></td>
                            <td>{{ $r->faultType?->localized_name ?? '-' }}</td>
                            <td>{{ $r->faultCause?->localized_name ?? '-' }}</td>
                            <td><x-status-badge :status="$r->status" /></td>
                            <td>{{ $r->created_at->format('Y-m-d') }}</td>
                            <td>{{ $r->completed_at?->format('Y-m-d') }}</td>
                            <td>{{ number_format((float) $r->cost_labor + (float) $r->cost_parts, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
