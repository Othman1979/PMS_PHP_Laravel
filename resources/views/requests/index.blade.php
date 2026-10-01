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
            <tbody>
                @forelse ($requests as $r)
                    <tr>
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
                        <td><x-status-badge :status="$r->priority" /></td>
                        <td><x-status-badge :status="$r->status" /></td>
                        <td>{{ $r->assignedTechnician?->full_name }}</td>
                        <td>{{ $r->created_at->format('Y-m-d H:i') }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('requests.show', $r) }}">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
</x-layouts.app>
