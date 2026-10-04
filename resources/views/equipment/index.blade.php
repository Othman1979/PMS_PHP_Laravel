@php $canManage = auth()->user()->canManage(); @endphp
<x-layouts.app :title="__('EquipmentRegistry')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">{{ __('EquipmentRegistry') }}</h2>
        @if ($canManage)
            <div class="d-flex flex-wrap gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Excel</button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('equipment.import.template') }}" data-no-dialog download>{{ __('DownloadTemplate') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('equipment.import.create') }}">{{ __('ImportEquipment') }}</a></li>
                    </ul>
                </div>
                <a class="btn btn-outline-dark" target="_blank" href="{{ route('equipment.labels', request()->only('category', 'status', 'q')) }}">{{ __('PrintQrLabels') }} ({{ $equipment->count() }})</a>
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

    @if ($canManage)
        <form id="labelsForm" method="get" action="{{ route('equipment.labels') }}" target="_blank" class="label-select-bar mb-2" hidden>
            <span class="fw-semibold"><span data-selected-count>0</span> {{ __('SelectedEquipment') }}</span>
            <button type="submit" class="btn btn-warning btn-sm fw-bold">{{ __('PrintSelectedLabels') }}</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-clear-selection>{{ __('ClearSelection') }}</button>
        </form>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    @if ($canManage)
                        <th class="label-check"><input type="checkbox" class="form-check-input" data-select-all title="{{ __('SelectAll') }}" aria-label="{{ __('SelectAll') }}"></th>
                    @endif
                    <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Department') }}</th>
                    <th>{{ __('Location') }}</th><th>{{ __('Status') }}</th><th>{{ __('Warranty') }}</th><th>{{ __('NextMaintenance') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($equipment as $e)
                    <tr>
                        @if ($canManage)
                            <td class="label-check"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $e->id }}" form="labelsForm" aria-label="{{ $e->code }}"></td>
                        @endif
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
                    <tr><td colspan="{{ $canManage ? 10 : 9 }}" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canManage)
        <script>
            (function () {
                var form = document.getElementById('labelsForm');
                var all = document.querySelector('[data-select-all]');
                var boxes = Array.prototype.slice.call(document.querySelectorAll('input[name="ids[]"]'));
                var count = form.querySelector('[data-selected-count]');

                function refresh() {
                    var n = boxes.filter(function (b) { return b.checked; }).length;
                    count.textContent = n;
                    form.hidden = n === 0;
                    all.checked = n > 0 && n === boxes.length;
                    all.indeterminate = n > 0 && n < boxes.length;
                    boxes.forEach(function (b) { b.closest('tr').classList.toggle('table-active', b.checked); });
                }

                all.addEventListener('change', function () {
                    boxes.forEach(function (b) { b.checked = all.checked; });
                    refresh();
                });
                boxes.forEach(function (b) { b.addEventListener('change', refresh); });
                form.querySelector('[data-clear-selection]').addEventListener('click', function () {
                    boxes.forEach(function (b) { b.checked = false; });
                    refresh();
                });
                refresh();
            })();
        </script>
    @endif
</x-layouts.app>
