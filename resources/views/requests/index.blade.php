<x-layouts.app :title="__('Requests')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>{{ $title }}</h2>
        <a class="btn btn-primary" href="{{ route('requests.create') }}">+ {{ __('NewRequest') }}</a>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="status" class="form-select">
                <option value="">{{ __('Status') }}</option>
                @foreach (\App\Enums\RequestStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <select name="department_id" class="form-select">
                <option value="">{{ __('Department') }}</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected($departmentId === $d->id)>{{ $d->localized_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <input type="text" name="user" value="{{ $createdBy }}" class="form-control" placeholder="{{ __('CreatedBy') }}">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary">{{ __('Filter') }}</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
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
                    <tr id="req-{{ $r->id }}" data-updated="{{ $r->updated_at?->toIso8601String() }}">
                        <td>
                            <a href="{{ route('requests.show', $r) }}">{{ $r->request_number }}</a>
                            @if ($r->is_preventive)
                                <span class="badge bg-info text-dark">{{ __('Preventive') }}</span>
                            @endif
                        </td>
                        <td class="text-truncate" style="max-width:260px">{{ $r->description }}</td>
                        <td>{{ $r->equipment?->name ?? '-' }}</td>
                        <td>{{ $r->department?->localized_name }}</td>
                        <td>{{ $r->createdBy?->full_name }}</td>
                        <td class="c-prio"><x-status-badge :status="$r->priority" /></td>
                        <td class="c-status"><x-status-badge :status="$r->status" /></td>
                        <td class="c-tech">{{ $r->assignedTechnician?->full_name }}</td>
                        <td>{{ $r->created_at->format('Y-m-d H:i') }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('requests.show', $r) }}">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="10" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}

<x-slot:scripts>
<script>
(function () {
    const live = window.PmsLive;
    if (!live || !live.enabled) return;
    const canInsert = @js($status === null && $departmentId === null && $createdBy === '' && $requests->onFirstPage());
    const channel = @js(auth()->user()->canManage() ? 'staff' : (auth()->user()->isTechnician() ? 'technician.'.auth()->id() : 'department.'.auth()->user()->department_id));
    const T = @js(['new' => __('NewRequest'), 'updated' => __('RequestUpdated'), 'view' => __('View'), 'preventive' => __('Preventive')]);
    const esc = live.esc, L = live.L;

    function badge(cell, cls, label, style) {
        const b = cell.querySelector('.badge') || cell.appendChild(document.createElement('span'));
        b.className = 'badge ' + cls;
        b.textContent = label;
        b.style.cssText = style || '';
    }

    live.on(channel, 'request.changed', d => {
        let tr = document.getElementById('req-' + d.id);
        if (!tr) {
            if (!canInsert) return;
            const tbody = document.getElementById('requestRows');
            tbody.querySelector('.empty-row')?.remove();
            tr = document.createElement('tr');
            tr.id = 'req-' + d.id;
            tr.innerHTML = '<td><a href="' + esc(d.detailsUrl) + '">' + esc(d.requestNumber) + '</a></td>' +
                '<td class="text-truncate" style="max-width:260px">' + esc(d.description) + '</td>' +
                '<td>' + esc(d.equipment || '-') + '</td><td>' + esc(L(d.department)) + '</td><td></td>' +
                '<td class="c-prio"></td><td class="c-status"></td><td class="c-tech"></td>' +
                '<td>' + esc(d.createdAt) + '</td>' +
                '<td><a class="btn btn-sm btn-outline-primary" href="' + esc(d.detailsUrl) + '">' + esc(T.view) + '</a></td>';
            tbody.prepend(tr);
        }
        badge(tr.querySelector('.c-prio'), d.priorityBadge, L(d.priorityLabel), d.priorityStyle);
        badge(tr.querySelector('.c-status'), d.statusBadge, L(d.statusLabel));
        tr.querySelector('.c-tech').textContent = d.technician || '';
        tr.classList.remove('row-flash'); void tr.offsetWidth; tr.classList.add('row-flash');
        live.toast(d.isNew ? T.new : T.updated + ' — ' + L(d.statusLabel), d.requestNumber + ' — ' + d.description, d.detailsUrl);
    });
})();
</script>
</x-slot:scripts>
</x-layouts.app>
