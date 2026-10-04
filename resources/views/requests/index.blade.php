<x-layouts.app :title="__('Requests')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>{{ $title }}</h2>
        <a class="btn btn-primary" href="{{ route('requests.create') }}">+ {{ __('NewRequest') }}</a>
    </div>

    @php
        $tabStatuses = [\App\Enums\RequestStatus::New, \App\Enums\RequestStatus::UnderReview, \App\Enums\RequestStatus::Assigned, \App\Enums\RequestStatus::Accepted,
            \App\Enums\RequestStatus::InProgress, \App\Enums\RequestStatus::WaitingParts, \App\Enums\RequestStatus::Completed, \App\Enums\RequestStatus::Reopened,
            \App\Enums\RequestStatus::Closed, \App\Enums\RequestStatus::Cancelled];
        $tabBase = collect($filters)->except(['status', 'overdue', 'page'])->all();
        $total = $counts->sum();
    @endphp
    <ul class="nav nav-pills status-tabs flex-nowrap overflow-auto mb-3" id="statusTabs">
        <li class="nav-item"><a class="nav-link {{ $status === null && ! $overdue ? 'active' : '' }}" href="{{ route('requests.index', $tabBase + ['all' => 1]) }}">{{ __('AllStatuses') }} <span class="badge rounded-pill text-bg-light" data-count="all">{{ $total }}</span></a></li>
        <li class="nav-item"><a class="nav-link text-danger {{ $overdue ? 'active' : '' }}" href="{{ route('requests.index', $tabBase + ['overdue' => 1]) }}">⏰ {{ __('Overdue') }} <span class="badge rounded-pill text-bg-danger" data-count="overdue">{{ $overdueCount }}</span></a></li>
        @foreach ($tabStatuses as $s)
            <li class="nav-item"><a class="nav-link {{ $status === $s ? 'active' : '' }}" href="{{ route('requests.index', $tabBase + ['status' => $s->value]) }}">{{ $s->label() }} <span class="badge rounded-pill text-bg-light" data-count="{{ $s->value }}">{{ $counts[$s->value] ?? 0 }}</span></a></li>
        @endforeach
    </ul>

    <form method="get" class="row g-2 mb-3 align-items-center">
        @if ($status) <input type="hidden" name="status" value="{{ $status->value }}"> @endif
        @if ($overdue) <input type="hidden" name="overdue" value="1"> @endif
        <div class="col-12 col-md-4 col-lg-3">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('SearchRequestsPlaceholder') }}">
        </div>
        <div class="col-6 col-md-auto">
            <select name="department_id" class="form-select">
                <option value="">{{ __('Department') }}</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected($departmentId === $d->id)>{{ $d->localized_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <input type="text" name="user" value="{{ $createdBy }}" class="form-control" placeholder="{{ __('CreatedBy') }}">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            @if ($filters !== [])
                <a class="btn btn-link btn-sm" href="{{ route('requests.index', ['reset' => 1]) }}">{{ __('ResetFilters') }}</a>
            @endif
        </div>
    </form>

    @php $bulk = auth()->user()->canManage(); @endphp
    @if ($bulk)
        <form method="post" action="{{ route('requests.bulk') }}" id="bulkForm" class="bulk-bar d-none align-items-center gap-2 flex-wrap mb-2">
            @csrf
            <span class="fw-semibold"><span id="bulkCount">0</span> {{ __('Selected') }}</span>
            <select name="action" class="form-select form-select-sm w-auto" id="bulkAction" required>
                <option value="assign">{{ __('BulkAssign') }}</option>
                <option value="cancel">{{ __('BulkCancel') }}</option>
                <option value="close">{{ __('BulkClose') }}</option>
            </select>
            <select name="technician_id" class="form-select form-select-sm w-auto" id="bulkTech">
                <option value="">{{ __('Technician') }}</option>
                @foreach ($technicians as $t)
                    <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                @endforeach
            </select>
            <input name="note" class="form-control form-control-sm w-auto" placeholder="{{ __('NoteOptional') }}" maxlength="1000">
            <button class="btn btn-sm btn-primary">{{ __('Apply') }}</button>
            <button type="button" class="btn btn-sm btn-link" id="bulkClear">{{ __('Cancel') }}</button>
            <span id="bulkIds"></span>
        </form>
    @endif
    <div class="table-responsive">
        <table class="table table-hover align-middle table-cards">
            <thead>
                <tr>
                    @if ($bulk)<th class="c-check"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="{{ __('SelectAll') }}"></th>@endif
                    <th>{{ __('RequestNumber') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Equipment') }}</th>
                    <th>{{ __('Department') }}</th>
                    <th>{{ __('CreatedBy') }}</th>
                    <th>{{ __('Priority') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Technician') }}</th>
                    <th>{{ __('CreatedAt') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="requestRows">
                @forelse ($requests as $r)
                    <tr id="req-{{ $r->id }}" data-status="{{ $r->status->value }}" data-updated="{{ $r->updated_at?->toIso8601String() }}">
                        @if ($bulk)<td class="c-check"><input type="checkbox" class="form-check-input row-check" value="{{ $r->id }}" aria-label="{{ $r->request_number }}"></td>@endif
                        <td data-label="{{ __('RequestNumber') }}">
                            <a href="{{ route('requests.show', $r) }}">{{ $r->request_number }}</a>
                            @if ($r->is_preventive)
                                <span class="badge bg-info text-dark">{{ __('Preventive') }}</span>
                            @endif
                        </td>
                        <td data-label="{{ __('Description') }}" class="text-truncate" style="max-width:260px">{{ $r->description }}</td>
                        <td data-label="{{ __('Equipment') }}">{{ $r->equipment?->name ?? '-' }}</td>
                        <td data-label="{{ __('Department') }}">{{ $r->department?->localized_name }}</td>
                        <td data-label="{{ __('CreatedBy') }}">{{ $r->createdBy?->full_name }}</td>
                        <td data-label="{{ __('Priority') }}" class="c-prio"><x-status-badge :status="$r->priority" /></td>
                        <td data-label="{{ __('Status') }}" class="c-status"><x-status-badge :status="$r->status" /> <x-due-badge :request="$r" /></td>
                        <td data-label="{{ __('Technician') }}" class="c-tech">{{ $r->assignedTechnician?->full_name }}</td>
                        <td data-label="{{ __('CreatedAt') }}">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                        <td class="c-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('requests.show', $r) }}">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="{{ $bulk ? 11 : 10 }}" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}

<x-slot:scripts>
@if ($bulk)
<script>
(function () {
    const form = document.getElementById('bulkForm');
    const ids = document.getElementById('bulkIds');
    const count = document.getElementById('bulkCount');
    const action = document.getElementById('bulkAction');
    const tech = document.getElementById('bulkTech');
    const all = document.getElementById('checkAll');
    function selected() { return [...document.querySelectorAll('.row-check:checked')].map(c => c.value); }
    function refresh() {
        const s = selected();
        count.textContent = s.length;
        ids.innerHTML = s.map(id => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
        form.classList.toggle('d-none', s.length === 0);
        form.classList.toggle('d-flex', s.length > 0);
    }
    function toggleTech() { tech.classList.toggle('d-none', action.value !== 'assign'); tech.required = action.value === 'assign'; }
    document.getElementById('requestRows').addEventListener('change', e => { if (e.target.classList.contains('row-check')) refresh(); });
    all.addEventListener('change', () => { document.querySelectorAll('.row-check').forEach(c => { c.checked = all.checked; }); refresh(); });
    document.getElementById('bulkClear').addEventListener('click', () => { document.querySelectorAll('.row-check').forEach(c => { c.checked = false; }); all.checked = false; refresh(); });
    action.addEventListener('change', toggleTech);
    form.addEventListener('submit', e => { if (!confirm(@js(__('AreYouSure')))) e.preventDefault(); });
    toggleTech();
})();
</script>
@endif
<script>
(function () {
    const live = window.PmsLive;
    if (!live || !live.enabled) return;
    const F = @js(['status' => $status?->value, 'overdue' => $overdue, 'departmentId' => $departmentId, 'countable' => $createdBy === '' && $search === '']);
    const canInsert = @js($status === null && ! $overdue && $departmentId === null && $createdBy === '' && $search === '' && $requests->onFirstPage());
    const H = @js(['no' => __('RequestNumber'), 'desc' => __('Description'), 'equip' => __('Equipment'), 'dept' => __('Department'), 'by' => __('CreatedBy'), 'prio' => __('Priority'), 'status' => __('Status'), 'tech' => __('Technician'), 'at' => __('CreatedAt'), 'overdue' => __('Overdue')]);
    const channel = @js(auth()->user()->canManage() ? 'staff' : (auth()->user()->isTechnician() ? 'technician.'.auth()->id() : 'department.'.auth()->user()->department_id));
    const T = @js(['new' => __('NewRequest'), 'updated' => __('RequestUpdated'), 'view' => __('View'), 'preventive' => __('Preventive')]);
    const esc = live.esc, L = live.L;
    const BULK = @js($bulk);

    function badge(cell, cls, label, style) {
        const b = cell.querySelector('.badge:not(.due-badge)') || cell.insertBefore(document.createElement('span'), cell.firstChild);
        b.className = 'badge ' + cls;
        b.textContent = label;
        b.style.cssText = style || '';
    }

    function bump(key, delta) {
        const el = document.querySelector('#statusTabs [data-count="' + key + '"]');
        if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
    }

    function dueBadge(cell, d) {
        cell.querySelector('.due-badge')?.remove();
        if (!d.overdue) return;
        const b = document.createElement('span');
        b.className = 'badge bg-danger due-badge';
        b.textContent = '⏰ ' + H.overdue;
        cell.appendChild(document.createTextNode(' '));
        cell.appendChild(b);
    }

    function bumpCounters(d) {
        if (!F.countable || (F.departmentId && d.departmentId !== F.departmentId)) return;
        if (d.isNew) {
            bump('all', 1);
            bump(d.status, 1);
        } else if (d.fromStatus && d.fromStatus !== d.status) {
            bump(d.fromStatus, -1);
            bump(d.status, 1);
        }
    }

    live.on(channel, 'request.changed', d => {
        bumpCounters(d);
        let tr = document.getElementById('req-' + d.id);
        if (tr && ((F.status && d.status !== F.status) || (F.overdue && !d.overdue))) {
            tr.remove();
            live.toast(T.updated + ' — ' + L(d.statusLabel), d.requestNumber + ' — ' + d.description, d.detailsUrl);
            return;
        }
        if (!tr) {
            if (!canInsert) return;
            const tbody = document.getElementById('requestRows');
            tbody.querySelector('.empty-row')?.remove();
            tr = document.createElement('tr');
            tr.id = 'req-' + d.id;
            tr.innerHTML = (BULK ? '<td class="c-check"><input type="checkbox" class="form-check-input row-check" value="' + d.id + '"></td>' : '') +
                '<td data-label="' + esc(H.no) + '"><a href="' + esc(d.detailsUrl) + '">' + esc(d.requestNumber) + '</a></td>' +
                '<td data-label="' + esc(H.desc) + '" class="text-truncate" style="max-width:260px">' + esc(d.description) + '</td>' +
                '<td data-label="' + esc(H.equip) + '">' + esc(d.equipment || '-') + '</td><td data-label="' + esc(H.dept) + '">' + esc(L(d.department)) + '</td><td data-label="' + esc(H.by) + '"></td>' +
                '<td data-label="' + esc(H.prio) + '" class="c-prio"></td><td data-label="' + esc(H.status) + '" class="c-status"></td><td data-label="' + esc(H.tech) + '" class="c-tech"></td>' +
                '<td data-label="' + esc(H.at) + '">' + esc(d.createdAt) + '</td>' +
                '<td class="c-actions"><a class="btn btn-sm btn-outline-primary" href="' + esc(d.detailsUrl) + '">' + esc(T.view) + '</a></td>';
            tr.dataset.status = d.status;
            tbody.prepend(tr);
        }
        tr.dataset.status = d.status;
        badge(tr.querySelector('.c-prio'), d.priorityBadge, L(d.priorityLabel), d.priorityStyle);
        badge(tr.querySelector('.c-status'), d.statusBadge, L(d.statusLabel));
        dueBadge(tr.querySelector('.c-status'), d);
        tr.querySelector('.c-tech').textContent = d.technician || '';
        tr.classList.remove('row-flash'); void tr.offsetWidth; tr.classList.add('row-flash');
        live.toast(d.isNew ? T.new : T.updated + ' — ' + L(d.statusLabel), d.requestNumber + ' — ' + d.description, d.detailsUrl);
    });
})();
</script>
</x-slot:scripts>
</x-layouts.app>
