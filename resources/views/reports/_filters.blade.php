<form method="get" action="{{ route('reports.show', $report->key()) }}" class="card card-body report-filters report-noprint mb-3">
    <div class="row g-2 align-items-end">
        @if ($filters->has('from'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_from">{{ __('From') }}</label>
                <input id="f_from" type="date" name="from" value="{{ $filters->from?->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_to">{{ __('To') }}</label>
                <input id="f_to" type="date" name="to" value="{{ $filters->to?->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
        @endif
        @if ($filters->has('department_id'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_department">{{ __('Department') }}</label>
                <select id="f_department" name="department_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected($filters->departmentId === $d->id)>{{ $d->localized_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($filters->has('equipment_id'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_equipment">{{ __('Equipment') }}</label>
                <select id="f_equipment" name="equipment_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($equipmentList as $e)
                        <option value="{{ $e->id }}" @selected($filters->equipmentId === $e->id)>{{ $e->code }} — {{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($filters->has('technician_id'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_technician">{{ __('Technician') }}</label>
                <select id="f_technician" name="technician_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($technicians as $t)
                        <option value="{{ $t->id }}" @selected($filters->technicianId === $t->id)>{{ $t->full_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($filters->has('fault_type_id'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_fault_type">{{ __('FaultType') }}</label>
                <select id="f_fault_type" name="fault_type_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($faultTypes as $t)
                        <option value="{{ $t->id }}" @selected($filters->faultTypeId === $t->id)>{{ $t->localized_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($filters->has('fault_cause_id'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_fault_cause">{{ __('FaultCause') }}</label>
                <select id="f_fault_cause" name="fault_cause_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($faultCauses as $c)
                        <option value="{{ $c->id }}" @selected($filters->faultCauseId === $c->id)>{{ $c->localized_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($filters->has('state'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_state">{{ __('RequestState') }}</label>
                <select id="f_state" name="state" class="form-select form-select-sm">
                    <option value="">—</option>
                    <option value="open" @selected($filters->state === 'open')>{{ __('Open') }}</option>
                    <option value="closed" @selected($filters->state === 'closed')>{{ __('ClosedRequests') }}</option>
                </select>
            </div>
        @endif
        @if ($filters->has('spare_part_id'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_part">{{ __('SparePart') }}</label>
                <select id="f_part" name="spare_part_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($spareParts as $p)
                        <option value="{{ $p->id }}" @selected($filters->sparePartId === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($filters->has('movement_type'))
            <div class="col-6 col-md-auto">
                <label class="form-label mb-1" for="f_movement">{{ __('MovementType') }}</label>
                <select id="f_movement" name="movement_type" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($movementTypes as $t)
                        <option value="{{ $t->value }}" @selected($filters->movementType === $t)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-fill">{{ __('RunReport') }}</button>
            <a href="{{ route('reports.show', $report->key()) }}" class="btn btn-outline-secondary btn-sm flex-fill">{{ __('Reset') }}</a>
        </div>
    </div>
</form>
