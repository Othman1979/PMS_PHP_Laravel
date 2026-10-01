<x-layouts.app :title="__('PMPlans')">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('PMPlans') }}</h2>
        <div>
            <form action="{{ route('pm.generate') }}" method="post" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary">{{ __('GenerateNow') }}</button>
            </form>
            <a class="btn btn-primary" href="{{ route('pm.create') }}">+ {{ __('AddPlan') }}</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ __('Equipment') }}</th><th>{{ __('Department') }}</th><th>{{ __('Description') }}</th><th>{{ __('Strategy') }}</th>
                    <th>{{ __('FrequencyDays') }}</th><th>{{ __('LastExecuted') }}</th><th>{{ __('NextDueDate') }}</th><th>{{ __('LinkedChecklist') }}</th>
                    <th>{{ __('Status') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $p)
                    <tr class="{{ $p->isOverdue() ? 'table-danger' : '' }}">
                        <td>{{ $p->equipment?->name }}<br><small class="text-muted">{{ $p->equipment?->code }}</small></td>
                        <td>{{ $p->equipment?->department?->localized_name }}</td>
                        <td>{{ $p->localized_task }}</td>
                        <td>{{ $p->strategy->label() }}</td>
                        <td>{{ $p->frequency_days }}</td>
                        <td>{{ $p->last_executed_date?->format('Y-m-d') }}</td>
                        <td>
                            {{ $p->next_due_date->format('Y-m-d') }}
                            @if ($p->isOverdue())
                                <span class="badge bg-danger">{{ __('Overdue') }}</span>
                            @elseif ($p->dueSoon())
                                <span class="badge bg-warning text-dark">{{ __('DueSoon') }}</span>
                            @endif
                        </td>
                        <td>{{ $p->checklist?->localized_name }}</td>
                        <td><span class="badge {{ $p->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $p->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td class="text-nowrap">
                            <form action="{{ route('pm.done', $p) }}" method="post" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success" title="{{ __('MarkDone') }}">✓</button>
                            </form>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('pm.edit', $p) }}">{{ __('Edit') }}</a>
                            <form action="{{ route('pm.destroy', $p) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
