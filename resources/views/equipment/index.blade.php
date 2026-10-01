@php $canManage = auth()->user()->canManage(); @endphp
<x-layouts.app :title="__('EquipmentRegistry')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>{{ __('EquipmentRegistry') }}</h2>
        @if ($canManage)
            <div class="d-flex gap-2">
                <a class="btn btn-outline-dark" target="_blank" href="{{ route('equipment.labels', request()->only('category', 'status', 'q')) }}">{{ __('PrintQrLabels') }}</a>
                <a class="btn btn-primary" href="{{ route('equipment.create') }}">+ {{ __('AddEquipment') }}</a>
            </div>
        @endif
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto"><input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('Search') }}"></div>
        <div class="col-auto">
            <select name="category" class="form-select">
                <option value="">{{ __('Category') }}</option>
                @foreach (\App\Enums\EquipmentCategory::cases() as $c)
                    <option value="{{ $c->value }}" @selected($category === $c)>{{ $c->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select">
                <option value="">{{ __('Status') }}</option>
                @foreach (\App\Enums\EquipmentStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary">{{ __('Filter') }}</button></div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Department') }}</th>
                    <th>{{ __('Location') }}</th><th>{{ __('Status') }}</th><th>{{ __('Warranty') }}</th><th>{{ __('NextMaintenance') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($equipment as $e)
                    <tr>
                        <td><code>{{ $e->code }}</code></td>
                        <td><a href="{{ route('equipment.show', $e) }}">{{ $e->name }}</a></td>
                        <td>{{ $e->category->label() }}</td>
                        <td>{{ $e->department?->localized_name }}</td>
                        <td>{{ $e->location }}</td>
                        <td><x-status-badge :status="$e->status" /></td>
                        <td>
                            @if ($e->isUnderWarranty())
                                <span class="badge bg-success">{{ $e->warranty_end->format('Y-m-d') }}</span>
                            @elseif ($e->warranty_end)
                                <span class="badge bg-secondary">{{ __('WarrantyExpired') }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $e->next_maintenance_date?->format('Y-m-d') }}</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('equipment.show', $e) }}">{{ __('View') }}</a>
                            @if ($canManage)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('equipment.edit', $e) }}">{{ __('Edit') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
