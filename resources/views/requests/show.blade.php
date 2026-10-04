@php
    use App\Enums\RequestStatus as S;
    $status = $mr->status;
    $isCoordinator = $user->canManage();
    $isAssignedTech = $mr->isAssignedTo($user);
    $isRequesterSide = $mr->isRequesterSide($user);
    $canComplete = $isAssignedTech && in_array($status, [S::InProgress, S::WaitingParts], true);
    $canAddNote = $isAssignedTech && in_array($status, [S::Accepted, S::InProgress, S::WaitingParts], true);
    $fmt = fn ($d) => $d?->format('Y-m-d H:i');
@endphp
<x-layouts.app :title="__('RequestDetails')">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3" data-live-reload="request.{{ $mr->id }}">
        <h2 class="h4 mb-0">
            {{ $mr->request_number }}
            <x-status-badge :status="$status" class="fs-6" />
            <x-status-badge :status="$mr->priority" class="fs-6" />
            <x-due-badge :request="$mr" class="fs-6" />
            @if ($mr->is_preventive)
                <span class="badge bg-info text-dark fs-6">{{ __('Preventive') }}</span>
            @endif
        </h2>
        <a href="{{ $user->isTechnician() ? route('requests.mine') : route('requests.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>

    @if ($mr->is_under_warranty || $mr->equipment?->isUnderWarranty())
        <div class="alert alert-warning">{{ str_replace('{0}', $mr->equipment?->warranty_end?->format('Y-m-d') ?? '-', __('AutoWarrantyNotice')) }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('RequestDetails') }}</strong></div>
                <div class="table-responsive">
                <table class="table mb-0">
                    <tr><th style="width:35%">{{ __('Description') }}</th><td style="white-space:pre-line">{{ $mr->description }}</td></tr>
                    <tr><th>{{ __('Department') }}</th><td>{{ $mr->department?->localized_name }}</td></tr>
                    <tr>
                        <th>{{ __('Equipment') }}</th>
                        <td>
                            @if ($mr->equipment)
                                <a href="{{ route('equipment.show', $mr->equipment) }}">{{ $mr->equipment->name }}</a>
                                <x-status-badge :status="$mr->equipment->status" />
                                @if ($mr->equipment->location)
                                    <div class="small text-muted">📍 {{ __('Location') }}: <strong>{{ $mr->equipment->location }}</strong></div>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @if ($mr->faultType || $mr->faultCause)
                        <tr><th>{{ __('FaultType') }} / {{ __('FaultCause') }}</th><td>{{ $mr->faultType?->localized_name ?? '-' }} / {{ $mr->faultCause?->localized_name ?? '-' }}</td></tr>
                    @endif
                    <tr>
                        <th>{{ __('Requester') }}</th>
                        <td>
                            {{ $mr->createdBy?->full_name }}
                            @if ($mr->createdBy?->phone)
                                <a class="btn btn-sm btn-outline-success ms-1" href="tel:{{ preg_replace('/[^0-9+]/', '', $mr->createdBy->phone) }}">📞 {{ __('Call') }} {{ $mr->createdBy->phone }}</a>
                            @endif
                        </td>
                    </tr>
                    <tr><th>{{ __('CreatedAt') }}</th><td>{{ $fmt($mr->created_at) }} <span class="text-muted small">({{ str_replace('{0}', $mr->created_at->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]), __('ElapsedSince')) }})</span></td></tr>
                    @if ($mr->due_at)
                        <tr><th>{{ __('DueAt') }}</th><td class="{{ $mr->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $fmt($mr->due_at) }} <x-due-badge :request="$mr" /></td></tr>
                    @endif
                    <tr><th>{{ __('AssignedTo') }}</th><td>{{ $mr->assignedTechnician?->full_name ?? '-' }} {{ $fmt($mr->assigned_at) }}</td></tr>
                    <tr><th>{{ __('AcceptedAt') }}</th><td>{{ $fmt($mr->accepted_at) }}</td></tr>
                    <tr><th>{{ __('StartedAt') }}</th><td>{{ $fmt($mr->started_at) }}</td></tr>
                    <tr><th>{{ __('CompletedAt') }}</th><td>{{ $fmt($mr->completed_at) }}</td></tr>
                    <tr><th>{{ __('Cost') }}</th><td>{{ __('CostLabor') }}: {{ number_format($mr->cost_labor, 2) }} | {{ __('CostParts') }}: {{ number_format($mr->cost_parts, 2) }} | <strong>{{ __('TotalCost') }}: {{ number_format($mr->totalCost(), 2) }}</strong></td></tr>
                    @if ($mr->resolution_notes)
                        <tr><th>{{ __('ResolutionNotes') }}</th><td style="white-space:pre-line">{{ $mr->resolution_notes }}</td></tr>
                    @endif
                    @if ($mr->technician_notes)
                        <tr><th>{{ __('TechnicianNotes') }}</th><td style="white-space:pre-line">{{ $mr->technician_notes }}</td></tr>
                    @endif
                    @if (in_array($status, [S::Completed, S::Closed], true) && $mr->department_confirmation)
                        <tr><th>{{ __('DepartmentConfirmation') }}</th><td>{{ $mr->department_confirmation->label() }}</td></tr>
                    @endif
                </table>
                </div>
            </div>

            @if ($mr->attachments->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('Attachments') }}</strong></div>
                    <div class="card-body">
                        @foreach ($mr->attachments as $a)
                            @if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $a->file_url))
                                <a href="{{ $a->file_url }}" target="_blank"><img src="{{ $a->file_url }}" alt="" class="img-thumbnail me-2 mb-2" style="max-height:120px"></a>
                            @else
                                <a href="{{ $a->file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary me-2 mb-2">{{ $a->file_name ?? basename($a->file_url) }}</a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($mr->partsUsed->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('SparePartsUsed') }}</strong></div>
                    <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>{{ __('SparePart') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('UnitCost') }}</th><th>{{ __('TotalCost') }}</th></tr></thead>
                        <tbody>
                            @foreach ($mr->partsUsed as $p)
                                <tr>
                                    <td>{{ $p->sparePart?->name }}</td>
                                    <td>{{ $p->quantity }}</td>
                                    <td>{{ number_format($p->unit_cost_at_use, 2) }}</td>
                                    <td>{{ number_format($p->quantity * $p->unit_cost_at_use, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @endif

            @if ($mr->checklistResults->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('ChecklistResults') }}</strong></div>
                    <div class="table-responsive">
                    <table class="table mb-0">
                        <tbody>
                            @foreach ($mr->checklistResults as $c)
                                <tr>
                                    <td>{{ $c->checklistItem?->localized_text }}</td>
                                    <td><span class="badge {{ $c->is_compliant ? 'bg-success' : 'bg-danger' }}">{{ $c->is_compliant ? __('Compliant') : __('NotCompliant') }}</span></td>
                                    <td>{{ $c->note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5 order-first order-lg-last">
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Actions') }}</strong></div>
                <div class="card-body">
                    @if ($isCoordinator && in_array($status, [S::New, S::Reopened], true))
                        <form action="{{ route('requests.review', $mr) }}" method="post" class="d-inline">
                            @csrf
                            <button class="btn btn-info btn-sm mb-2">{{ __('MarkUnderReview') }}</button>
                        </form>
                    @endif

                    @if ($isCoordinator && $status === S::Reopened && $mr->department_confirmation === \App\Enums\DepartmentConfirmation::NotResolved)
                        <div class="alert alert-warning small">{{ __('NotResolvedNextStepStaff') }}</div>
                    @endif
                    @if ($isCoordinator && in_array($status, [S::New, S::UnderReview, S::Reopened], true))
                        <form action="{{ route('requests.assign', $mr) }}" method="post" class="mb-3">
                            @csrf
                            <label class="form-label" for="technician_id">{{ __('AssignTechnician') }}</label>
                            <select id="technician_id" name="technician_id" class="form-select mb-1" required>
                                <option value="">{{ __('AssignTechnician') }}</option>
                                @foreach ($technicians as $t)
                                    <option value="{{ $t->id }}" @selected($suggested?->id === $t->id)>{{ $t->full_name }}{{ $t->specialty ? ' — '.$t->specialty->label() : '' }}</option>
                                @endforeach
                            </select>
                            @if ($suggested)
                                <div class="form-text mb-2">{{ __('SuggestedTechnician') }}: <strong>{{ $suggested->full_name }}</strong></div>
                            @endif
                            <textarea name="note" class="form-control mb-2" rows="2" placeholder="{{ __('NoteForTechnician') }}"></textarea>
                            <button class="btn btn-primary w-100">{{ __('Assign') }}</button>
                        </form>
                    @endif

                    @if ($isAssignedTech && $status === S::Assigned)
                        @if ($mr->department_confirmation === \App\Enums\DepartmentConfirmation::NotResolved)
                            <div class="alert alert-warning small">{{ __('NotResolvedNextStep') }}</div>
                        @endif
                        <form action="{{ route('requests.accept-start', $mr) }}" method="post" class="mb-2" id="acceptStartForm">
                            @csrf
                            <p class="small text-muted mb-2">{{ __('AcceptAndStartHint') }}</p>
                            <textarea name="note" class="form-control mb-2" rows="2" placeholder="{{ __('NoteOptional') }}"></textarea>
                            <button class="btn btn-warning btn-lg w-100">▶ {{ __('AcceptAndStart') }}</button>
                        </form>
                        <form action="{{ route('requests.accept', $mr) }}" method="post" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-primary w-100">{{ __('AcceptOnly') }}</button>
                        </form>
                    @endif

                    @if ($isAssignedTech && $status === S::Accepted)
                        <form action="{{ route('requests.start', $mr) }}" method="post" class="mb-3">
                            @csrf
                            <p class="small text-muted mb-2">{{ __('StartHint') }}</p>
                            <textarea name="note" class="form-control mb-2" rows="2" placeholder="{{ __('NoteOptional') }}"></textarea>
                            <button class="btn btn-warning btn-lg w-100">{{ __('StartWork') }}</button>
                        </form>
                    @endif

                    @if ($isAssignedTech && $status === S::InProgress)
                        <form action="{{ route('requests.wait-parts', $mr) }}" method="post" class="d-flex gap-2 mb-3">
                            @csrf
                            <input name="note" class="form-control" placeholder="{{ __('NoteOptional') }}">
                            <button class="btn btn-outline-warning text-nowrap">{{ __('WaitForParts') }}</button>
                        </form>
                    @endif

                    @if ($isAssignedTech && $status === S::WaitingParts)
                        <form action="{{ route('requests.resume', $mr) }}" method="post" class="mb-3">
                            @csrf
                            <button class="btn btn-warning btn-lg w-100">{{ __('ResumeWork') }}</button>
                        </form>
                    @endif

                    @if ($canAddNote)
                        <form action="{{ route('requests.note', $mr) }}" method="post" class="mb-3">
                            @csrf
                            <label class="form-label" for="progress-note">{{ __('AddNote') }}</label>
                            <textarea id="progress-note" name="note" class="form-control mb-2" rows="3" required placeholder="{{ __('ProgressNotePlaceholder') }}"></textarea>
                            <button class="btn btn-outline-primary w-100">{{ __('AddNote') }}</button>
                        </form>
                    @endif

                    @if ($status === S::Completed && $isRequesterSide)
                        <div class="alert alert-info">{{ __('CompletedAwaitingConfirmation') }}</div>
                        <form action="{{ route('requests.confirm', $mr) }}" method="post" class="d-inline">
                            @csrf
                            <input type="hidden" name="resolved" value="1">
                            <button class="btn btn-success btn-sm mb-2">{{ __('MarkResolved') }}</button>
                        </form>
                        <form action="{{ route('requests.confirm', $mr) }}" method="post" class="d-inline">
                            @csrf
                            <input type="hidden" name="resolved" value="0">
                            <button class="btn btn-danger btn-sm mb-2">{{ __('MarkNotResolved') }}</button>
                        </form>
                    @endif

                    @if ($isCoordinator && in_array($status, [S::Completed, S::Reopened], true))
                        <form action="{{ route('requests.close', $mr) }}" method="post" class="d-inline">
                            @csrf
                            <button class="btn btn-dark btn-sm mb-2">{{ __('CloseRequest') }}</button>
                        </form>
                    @endif

                    @if ($status === S::Closed && ($isRequesterSide || $isCoordinator))
                        <form action="{{ route('requests.reopen', $mr) }}" method="post" class="row g-2 mb-2">
                            @csrf
                            <div class="col-auto flex-grow-1"><input name="note" class="form-control form-control-sm" placeholder="{{ __('NoteOptional') }}"></div>
                            <div class="col-auto"><button class="btn btn-outline-danger btn-sm">{{ __('ReopenRequest') }}</button></div>
                        </form>
                    @endif

                    @if (! in_array($status, [S::Closed, S::Cancelled, S::Completed], true) && ($isRequesterSide || $isCoordinator))
                        <form action="{{ route('requests.cancel', $mr) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm mb-2">{{ __('CancelRequest') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($canComplete)
                <div class="card mb-3 border-success">
                    <div class="card-header bg-success text-white"><strong>{{ __('MarkComplete') }}</strong></div>
                    <div class="card-body">
                        <form action="{{ route('requests.complete', $mr) }}" method="post" id="completeForm" data-draft-key="pms.draft.complete.{{ $mr->id }}">
                            <div class="alert alert-info py-2 small d-none justify-content-between align-items-center" id="draftNotice">
                                <span>{{ __('DraftRestored') }}</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="draftDiscard">{{ __('DiscardDraft') }}</button>
                            </div>
                            @csrf
                            @if ($checklist)
                                <h6>{{ __('FillChecklist') }}: {{ $checklist->localized_name }}</h6>
                                @foreach ($checklist->items as $item)
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" name="check[{{ $item->id }}]" value="1" id="check_{{ $item->id }}">
                                        <label class="form-check-label" for="check_{{ $item->id }}">{{ $item->localized_text }}</label>
                                        <input class="form-control form-control-sm" name="check_note[{{ $item->id }}]" placeholder="{{ __('NoteOptional') }}">
                                    </div>
                                @endforeach
                                <hr>
                            @endif
                            @if ($faultTypes->isNotEmpty())
                                <div class="mb-2">
                                    <label class="form-label" for="fault_type_id">{{ __('FaultType') }}</label>
                                    <select id="fault_type_id" name="fault_type_id" class="form-select" required>
                                        <option value="">—</option>
                                        @foreach ($faultTypes as $t)
                                            <option value="{{ $t->id }}" @selected((int) old('fault_type_id', $mr->fault_type_id) === $t->id)>{{ $t->localized_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if ($faultCauses->isNotEmpty())
                                <div class="mb-2">
                                    <label class="form-label" for="fault_cause_id">{{ __('FaultCause') }}</label>
                                    <select id="fault_cause_id" name="fault_cause_id" class="form-select" required>
                                        <option value="">—</option>
                                        @foreach ($faultCauses as $c)
                                            <option value="{{ $c->id }}" @selected((int) old('fault_cause_id') === $c->id)>{{ $c->localized_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-2">
                                <label class="form-label" for="resolution_notes">{{ __('ResolutionNotes') }}</label>
                                <textarea id="resolution_notes" name="resolution_notes" class="form-control" rows="3" required placeholder="{{ __('CompleteHint') }}">{{ old('resolution_notes') }}</textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="technician_notes">{{ __('TechnicianNotes') }}</label>
                                <textarea id="technician_notes" name="technician_notes" class="form-control" rows="2">{{ old('technician_notes') }}</textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="cost_labor">{{ __('CostLabor') }}</label>
                                <input id="cost_labor" name="cost_labor" type="number" step="0.01" min="0" class="form-control" value="{{ old('cost_labor', 0) }}">
                            </div>
                            <label class="form-label">{{ __('SparePartsUsed') }}</label>
                            <div id="partsContainer">
                                <div class="row g-2 mb-1 part-row">
                                    <div class="col-8">
                                        <input type="hidden" name="parts[0][spare_part_id]" class="part-id">
                                        <input type="text" list="partsList" class="form-control form-control-sm part-search" placeholder="{{ __('SearchPartPlaceholder') }}" autocomplete="off">
                                    </div>
                                    <div class="col-4">
                                        <input name="parts[0][quantity]" type="number" min="0" class="form-control form-control-sm part-qty" placeholder="{{ __('Quantity') }}">
                                    </div>
                                </div>
                            </div>
                            <datalist id="partsList">
                                @foreach ($spareParts as $sp)
                                    @if ($sp->quantity > 0)
                                        <option value="{{ $sp->name }}{{ $sp->part_number ? ' ('.$sp->part_number.')' : '' }} — {{ __('InStock') }}: {{ $sp->quantity }}" data-id="{{ $sp->id }}"></option>
                                    @endif
                                @endforeach
                            </datalist>
                            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addPart">+ {{ __('AddPart') }}</button>
                            <button type="submit" class="btn btn-success btn-lg w-100">{{ __('MarkComplete') }}</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($status !== S::Cancelled)
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('AddComment') }}</strong></div>
                    <div class="card-body">
                        <form action="{{ route('requests.comment', $mr) }}" method="post">
                            @csrf
                            <textarea name="note" class="form-control mb-2" rows="2" required maxlength="1000" placeholder="{{ __('CommentPlaceholder') }}"></textarea>
                            <button class="btn btn-outline-primary">{{ __('AddComment') }}</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><strong>{{ __('Timeline') }}</strong></div>
                <ul class="list-group list-group-flush">
                    @foreach ($mr->timeline as $t)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span>
                                    @if ($t->status_from && $t->status_from === $t->status_to)
                                        <span class="badge bg-light text-dark border">{{ $t->changedBy?->isTechnician() ? __('Note') : __('Comments') }}</span>
                                    @else
                                        @if ($t->status_from)
                                            <x-status-badge :status="$t->status_from" /> <span>→</span>
                                        @endif
                                        <x-status-badge :status="$t->status_to" />
                                    @endif
                                </span>
                                <small class="text-muted">{{ $fmt($t->changed_at) }}</small>
                            </div>
                            <div class="small text-muted">{{ $t->changedBy?->full_name ?? __('System') }}</div>
                            @if ($t->note)
                                <div class="mt-1" style="white-space:pre-line">{{ $t->note }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <x-slot:scripts>
        <script>
            (function () {
                const form = document.getElementById('completeForm');
                if (!form) return;
                const container = document.getElementById('partsContainer');
                const options = [...document.querySelectorAll('#partsList option')];

                /* searchable parts: map the typed label back to the spare-part id */
                function wireRow(row) {
                    const search = row.querySelector('.part-search');
                    const id = row.querySelector('.part-id');
                    const sync = () => {
                        const hit = options.find(o => o.value === search.value.trim());
                        id.value = hit ? hit.dataset.id : '';
                        search.classList.toggle('is-invalid', search.value.trim() !== '' && !hit);
                    };
                    search.addEventListener('input', sync);
                    search.addEventListener('change', sync);
                }
                container.querySelectorAll('.part-row').forEach(wireRow);
                let i = container.querySelectorAll('.part-row').length;
                function addRow() {
                    const row = container.querySelector('.part-row').cloneNode(true);
                    row.querySelector('.part-id').name = 'parts[' + i + '][spare_part_id]';
                    row.querySelector('.part-id').value = '';
                    row.querySelector('.part-search').value = '';
                    row.querySelector('.part-search').classList.remove('is-invalid');
                    row.querySelector('.part-qty').name = 'parts[' + i + '][quantity]';
                    row.querySelector('.part-qty').value = '';
                    container.appendChild(row);
                    wireRow(row);
                    i++;
                    return row;
                }
                document.getElementById('addPart').addEventListener('click', addRow);

                /* draft autosave (phone may lock / tab may close mid-form) */
                const key = form.dataset.draftKey;
                const fields = ['fault_type_id', 'fault_cause_id', 'resolution_notes', 'technician_notes', 'cost_labor'];
                const notice = document.getElementById('draftNotice');
                function snapshot() {
                    const d = {};
                    fields.forEach(n => { const el = form.elements[n]; if (el) d[n] = el.value; });
                    d.parts = [...container.querySelectorAll('.part-row')]
                        .map(r => ({ s: r.querySelector('.part-search').value, q: r.querySelector('.part-qty').value }))
                        .filter(p => p.s || p.q);
                    return d;
                }
                function hasContent(d) {
                    return fields.some(n => n !== 'cost_labor' && (d[n] || '') !== '') || (d.cost_labor && d.cost_labor !== '0') || d.parts.length > 0;
                }
                let saveTimer = null;
                form.addEventListener('input', () => {
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(() => {
                        const d = snapshot();
                        try { hasContent(d) ? localStorage.setItem(key, JSON.stringify(d)) : localStorage.removeItem(key); } catch (e) { /* storage full / disabled */ }
                    }, 300);
                });
                form.addEventListener('submit', () => { try { localStorage.removeItem(key); } catch (e) { /* ignore */ } });
                document.getElementById('draftDiscard').addEventListener('click', () => {
                    try { localStorage.removeItem(key); } catch (e) { /* ignore */ }
                    form.reset();
                    container.querySelectorAll('.part-row:not(:first-child)').forEach(r => r.remove());
                    notice.classList.replace('d-flex', 'd-none');
                });
                try {
                    const saved = JSON.parse(localStorage.getItem(key) || 'null');
                    if (saved && hasContent(saved) && !hasContent(snapshot())) {
                        fields.forEach(n => { const el = form.elements[n]; if (el && saved[n] !== undefined) el.value = saved[n]; });
                        (saved.parts || []).forEach((p, idx) => {
                            const row = idx === 0 ? container.querySelector('.part-row') : addRow();
                            row.querySelector('.part-search').value = p.s || '';
                            row.querySelector('.part-qty').value = p.q || '';
                            row.querySelector('.part-search').dispatchEvent(new Event('change'));
                        });
                        notice.classList.replace('d-none', 'd-flex');
                    }
                } catch (e) { /* corrupt draft */ }
            })();
        </script>
    </x-slot:scripts>
</x-layouts.app>
