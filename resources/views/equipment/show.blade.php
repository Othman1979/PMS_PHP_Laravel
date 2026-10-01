@php
    $user = auth()->user();
    $canManage = $user->canManage();
    $d = fn ($v) => $v?->format('Y-m-d');
@endphp
<x-layouts.app :title="__('EquipmentDetails')">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ $equipment->name }} <small class="text-muted">{{ $equipment->code }}</small></h2>
        <div>
            <a class="btn btn-primary" href="{{ route('requests.create', ['equipment_id' => $equipment->id]) }}">+ {{ __('NewRequest') }}</a>
            @if ($canManage)
                <a class="btn btn-outline-secondary" href="{{ route('equipment.edit', $equipment) }}">{{ __('Edit') }}</a>
            @endif
        </div>
    </div>

    @if ($equipment->isUnderWarranty())
        <div class="alert alert-success">
            {{ str_replace('{0}', $d($equipment->warranty_end), __('AutoWarrantyNotice')) }}
            @if ($equipment->warranty_provider)<span>({{ $equipment->warranty_provider }})</span>@endif
        </div>
    @elseif ($equipment->warranty_end)
        <div class="alert alert-secondary">{{ __('WarrantyExpired') }}: {{ $d($equipment->warranty_end) }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>{{ __('EquipmentInfo') }}</strong></div>
                <table class="table mb-0">
                    <tr><th>{{ __('Code') }}</th><td><code>{{ $equipment->code }}</code></td></tr>
                    <tr><th>{{ __('Category') }}</th><td>{{ $equipment->category->label() }}</td></tr>
                    <tr><th>{{ __('Department') }}</th><td>{{ $equipment->department?->localized_name }}</td></tr>
                    <tr><th>{{ __('Location') }}</th><td>{{ $equipment->location }}</td></tr>
                    <tr><th>{{ __('Status') }}</th><td><x-status-badge :status="$equipment->status" /></td></tr>
                    <tr><th>{{ __('Manufacturer') }}</th><td>{{ $equipment->manufacturer }}</td></tr>
                    <tr><th>{{ __('Model') }}</th><td>{{ $equipment->model }}</td></tr>
                    <tr><th>{{ __('SerialNumber') }}</th><td>{{ $equipment->serial_number }}</td></tr>
                    <tr><th>{{ __('PurchaseDate') }}</th><td>{{ $d($equipment->purchase_date) }}</td></tr>
                    <tr><th>{{ __('Vendor') }}</th><td>{{ $equipment->vendor }}</td></tr>
                    <tr><th>{{ __('PurchasePrice') }}</th><td>{{ $equipment->purchase_price !== null ? number_format($equipment->purchase_price, 2) : '' }}</td></tr>
                    <tr><th>{{ __('LastMaintenance') }}</th><td>{{ $d($equipment->last_maintenance_date) }}</td></tr>
                    <tr><th>{{ __('NextMaintenance') }}</th><td>{{ $d($equipment->next_maintenance_date) }}</td></tr>
                    @if ($equipment->manual_file_url)
                        <tr><th>{{ __('Manual') }}</th><td><a href="{{ $equipment->manual_file_url }}" target="_blank">{{ __('View') }}</a></td></tr>
                    @endif
                    @if ($equipment->photo_url)
                        <tr><th>{{ __('Attachments') }}</th><td><a href="{{ $equipment->photo_url }}" target="_blank"><img src="{{ $equipment->photo_url }}" alt="" style="max-height:120px" class="img-thumbnail"></a></td></tr>
                    @endif
                </table>
            </div>
            @if ($equipment->notes)
                <div class="card mt-3">
                    <div class="card-header"><strong>{{ __('Notes') }}</strong></div>
                    <div class="card-body" style="white-space:pre-line">{{ $equipment->notes }}</div>
                </div>
            @endif
        </div>

        <div class="col-md-6">
            @if ($canManage)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>{{ __('QrCode') }}</strong>
                        <a class="btn btn-sm btn-warning fw-bold" href="{{ route('equipment.labels', ['id' => $equipment->id]) }}" target="_blank">{{ __('PrintLabel') }}</a>
                    </div>
                    <div class="card-body d-flex gap-3 align-items-center">
                        <img src="{{ route('equipment.qr', $equipment) }}" alt="QR" width="132" height="132">
                        <div class="small">
                            <div class="text-muted mb-2">{{ __('QrHint') }}</div>
                            <a href="{{ $quickUrl }}" target="_blank" class="text-break">{{ $quickUrl }}</a>
                        </div>
                    </div>
                </div>
            @endif
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('WarrantyInfo') }}</strong></div>
                <table class="table mb-0">
                    <tr><th>{{ __('WarrantyStart') }}</th><td>{{ $d($equipment->warranty_start) }}</td></tr>
                    <tr><th>{{ __('WarrantyEnd') }}</th><td>{{ $d($equipment->warranty_end) }}</td></tr>
                    <tr><th>{{ __('WarrantyProvider') }}</th><td>{{ $equipment->warranty_provider }}</td></tr>
                    <tr><th>{{ __('WarrantyNumber') }}</th><td>{{ $equipment->warranty_number }}</td></tr>
                    @if ($equipment->warranty_document_url)
                        <tr><th>{{ __('Attachments') }}</th><td><a href="{{ $equipment->warranty_document_url }}" target="_blank">{{ __('View') }}</a></td></tr>
                    @endif
                </table>
            </div>
            @if ($equipment->pmPlans->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('PMPlans') }}</strong></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($equipment->pmPlans as $p)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $p->localized_task }} ({{ $p->strategy->label() }})</span>
                                <span class="badge {{ $p->isOverdue() ? 'bg-danger' : 'bg-info text-dark' }}">{{ $d($p->next_due_date) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><strong>{{ __('MaintenanceHistory') }}</strong></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>{{ __('RequestNumber') }}</th><th>{{ __('Description') }}</th><th>{{ __('Status') }}</th><th>{{ __('Technician') }}</th><th>{{ __('Cost') }}</th><th>{{ __('CreatedAt') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($equipment->maintenanceRequests as $r)
                        <tr>
                            <td><a href="{{ route('requests.show', $r) }}">{{ $r->request_number }}</a></td>
                            <td>{{ \Illuminate\Support\Str::limit($r->description, 120) }}</td>
                            <td><x-status-badge :status="$r->status" /></td>
                            <td>{{ $r->assignedTechnician?->full_name }}</td>
                            <td>{{ number_format($r->totalCost(), 2) }}</td>
                            <td>{{ $r->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($user->isAdmin())
        <form action="{{ route('equipment.destroy', $equipment) }}" method="post" class="mt-3" onsubmit="return confirm(@js(__('AreYouSure')))">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
        </form>
    @endif
</x-layouts.app>
