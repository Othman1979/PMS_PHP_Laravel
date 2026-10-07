@php
    $user = auth()->user();
    $canManage = $user->canManage();
    $canCalibrate = $canManage || $user->isFoodSafety();
    $canCommission = $user->canApproveFoodSafety();
    $calibration = $equipment->calibrationStatus();
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

    @if ($equipment->requiresCommissioning())
        <div class="alert alert-warning"><strong>{{ __('AwaitingCommissioning') }}:</strong> {{ __('CommissioningHint') }}</div>
    @endif
    @if ($calibration === \App\Enums\CalibrationStatus::Expired)
        <div class="alert alert-danger"><strong>{{ __('Calibration_Expired') }}:</strong> {{ str_replace('{0}', $d($equipment->next_calibration_date), __('CalibrationExpiredNotice')) }}</div>
    @elseif ($calibration === \App\Enums\CalibrationStatus::DueSoon)
        <div class="alert alert-warning">{{ str_replace('{0}', $d($equipment->next_calibration_date), __('CalibrationDueSoonNotice')) }}</div>
    @endif

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
                    <tr><th>{{ __('Status') }}</th><td><x-status-badge :status="$equipment->status" /> <x-food-safety-badges :equipment="$equipment" :calibration="false" /></td></tr>
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
            <div class="card mb-3 {{ $equipment->requiresCommissioning() ? 'border-warning' : '' }}">
                <div class="card-header"><strong>{{ __('FoodSafetySection') }}</strong></div>
                <table class="table mb-0">
                    <tr><th style="width:45%">{{ __('FoodContact') }}</th><td>{{ $equipment->food_contact ? __('Yes') : __('No') }}</td></tr>
                    <tr><th>{{ __('CriticalEquipment') }}</th><td>{{ $equipment->is_critical ? __('Yes') : __('No') }}</td></tr>
                    <tr><th>{{ __('CcpReference') }}</th><td>{{ $equipment->ccp_reference ?: '-' }}</td></tr>
                    <tr><th>{{ __('HygienicDesign') }}</th><td>{{ $equipment->hygienic_design ? __('Yes') : __('No') }}</td></tr>
                    <tr><th>{{ __('PurchaseSpec') }}</th><td>@if ($equipment->purchase_spec_url)<a href="{{ $equipment->purchase_spec_url }}" target="_blank">{{ __('View') }}</a>@else<span class="text-muted">{{ __('NotAttached') }}</span>@endif</td></tr>
                    <tr><th>{{ __('ConformityDoc') }}</th><td>@if ($equipment->conformity_doc_url)<a href="{{ $equipment->conformity_doc_url }}" target="_blank">{{ __('View') }}</a>@else<span class="text-muted">{{ __('NotAttached') }}</span>@endif</td></tr>
                    <tr>
                        <th>{{ __('Commissioning') }}</th>
                        <td>
                            @if ($equipment->commissioned_at)
                                <span class="badge bg-success">{{ __('Commissioned') }}</span>
                                <div class="small text-muted">{{ $equipment->commissioned_at->format('Y-m-d H:i') }} — {{ $equipment->commissionedBy?->full_name ?? '-' }}</div>
                                @if ($equipment->commissioning_notes)<div class="small" style="white-space:pre-line">{{ $equipment->commissioning_notes }}</div>@endif
                            @elseif ($equipment->isFoodSafetyRelevant())
                                <span class="badge bg-warning text-dark">{{ __('AwaitingCommissioning') }}</span>
                            @else
                                <span class="text-muted">{{ __('NotRequired') }}</span>
                            @endif
                        </td>
                    </tr>
                </table>
                @if ($equipment->requiresCommissioning() && $canCommission)
                    <div class="card-body border-top">
                        <form action="{{ route('equipment.commission', $equipment) }}" method="post">
                            @csrf
                            @unless ($equipment->hasCommissioningDocuments())
                                <div class="alert alert-warning small">{{ __('Error_CommissioningDocs') }}</div>
                            @endunless
                            <label class="form-label" for="commissioning_notes">{{ __('TrialRunNotes') }}</label>
                            <textarea id="commissioning_notes" name="commissioning_notes" class="form-control mb-2 @error('commissioning_notes') is-invalid @enderror" rows="3" required maxlength="2000" placeholder="{{ __('TrialRunPlaceholder') }}">{{ old('commissioning_notes') }}</textarea>
                            @error('commissioning_notes')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button class="btn btn-success w-100" @disabled(! $equipment->hasCommissioningDocuments())>{{ __('ApproveCommissioning') }}</button>
                        </form>
                    </div>
                @endif
            </div>

            @if ($equipment->is_measuring_device || $canCalibrate)
                <div class="card mb-3 {{ $calibration === \App\Enums\CalibrationStatus::Expired ? 'border-danger' : '' }}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>{{ __('Calibration') }}</strong>
                        <span class="badge {{ $calibration->badge() }}">{{ $calibration->label() }}</span>
                    </div>
                    @if ($equipment->is_measuring_device)
                        <table class="table mb-0">
                            <tr><th style="width:45%">{{ __('CalibrationIntervalDays') }}</th><td>{{ $equipment->calibration_interval_days ?? '-' }}</td></tr>
                            <tr><th>{{ __('LastCalibration') }}</th><td>{{ $d($equipment->last_calibration_date) ?? '-' }}</td></tr>
                            <tr><th>{{ __('NextCalibration') }}</th><td class="{{ $calibration->needsAttention() ? 'fw-semibold text-danger' : '' }}">{{ $d($equipment->next_calibration_date) ?? '-' }}</td></tr>
                            <tr><th>{{ __('CalibrationProvider') }}</th><td>{{ $equipment->calibration_provider ?? '-' }}</td></tr>
                            @if ($equipment->calibration_certificate_url)
                                <tr><th>{{ __('CalibrationCertificate') }}</th><td><a href="{{ $equipment->calibration_certificate_url }}" target="_blank">{{ __('View') }}</a></td></tr>
                            @endif
                        </table>
                    @endif
                    @if ($equipment->calibrations->isNotEmpty())
                        <div class="table-responsive border-top">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Result') }}</th><th>{{ __('NextCalibration') }}</th><th>{{ __('CalibrationProvider') }}</th><th>{{ __('Certificate') }}</th><th>{{ __('User') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($equipment->calibrations as $c)
                                        <tr>
                                            <td>{{ $d($c->calibrated_at) }}</td>
                                            <td><span class="badge {{ $c->result->badge() }}">{{ $c->result->label() }}</span></td>
                                            <td>{{ $d($c->next_due_date) ?? '-' }}</td>
                                            <td>{{ $c->provider ?? '-' }}</td>
                                            <td>@if ($c->certificate_url)<a href="{{ $c->certificate_url }}" target="_blank">{{ $c->certificate_number ?? __('View') }}</a>@else{{ $c->certificate_number ?? '-' }}@endif</td>
                                            <td class="small text-muted">{{ $c->recordedBy?->full_name ?? '-' }}@if ($c->notes)<div>{{ $c->notes }}</div>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if ($canCalibrate)
                        <div class="card-body border-top">
                            <details {{ $errors->hasAny(['calibrated_at', 'next_due_date', 'result']) ? 'open' : '' }}>
                                <summary class="fw-semibold mb-2" style="cursor:pointer">{{ __('RecordCalibration') }}</summary>
                                <form action="{{ route('equipment.calibrate', $equipment) }}" method="post" enctype="multipart/form-data" class="row g-2">
                                    @csrf
                                    <div class="col-6">
                                        <label class="form-label small" for="calibrated_at">{{ __('CalibrationDate') }}</label>
                                        <input id="calibrated_at" name="calibrated_at" type="date" value="{{ old('calibrated_at', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small" for="next_due_date">{{ __('NextCalibration') }}</label>
                                        <input id="next_due_date" name="next_due_date" type="date" value="{{ old('next_due_date') }}" class="form-control form-control-sm">
                                        <div class="form-text">{{ __('NextCalibrationHint') }}</div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small" for="result">{{ __('Result') }}</label>
                                        <select id="result" name="result" class="form-select form-select-sm">
                                            @foreach (\App\Enums\CalibrationResult::cases() as $r)
                                                <option value="{{ $r->value }}" @selected(old('result') === $r->value)>{{ $r->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small" for="provider">{{ __('CalibrationProvider') }}</label>
                                        <input id="provider" name="provider" value="{{ old('provider', $equipment->calibration_provider) }}" class="form-control form-control-sm" maxlength="200">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small" for="certificate_number">{{ __('CertificateNumber') }}</label>
                                        <input id="certificate_number" name="certificate_number" value="{{ old('certificate_number') }}" class="form-control form-control-sm" maxlength="100">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small" for="certificate">{{ __('CalibrationCertificate') }}</label>
                                        <input id="certificate" name="certificate" type="file" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12">
                                        <input name="notes" value="{{ old('notes') }}" class="form-control form-control-sm" maxlength="1000" placeholder="{{ __('Notes') }}">
                                    </div>
                                    <div class="col-12"><button class="btn btn-primary btn-sm w-100">{{ __('RecordCalibration') }}</button></div>
                                </form>
                            </details>
                        </div>
                    @endif
                </div>
            @endif

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
