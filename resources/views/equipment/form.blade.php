@php
    $isEdit = $equipment->exists;
    $hasWarranty = (bool) old('has_warranty', $equipment->has_warranty);
    $val = fn (string $f) => old($f, $equipment->{$f});
    $date = fn (string $f) => old($f, $equipment->{$f}?->format('Y-m-d'));
    $selCategory = old('category', $equipment->category?->value);
    $selStatus = old('status', $equipment->status?->value);
    $flag = fn (string $f) => (bool) old($f, $equipment->{$f});
    $isMeasuring = $flag('is_measuring_device');
    $commissioned = $equipment->commissioned_at !== null;
@endphp
<x-layouts.app :title="$isEdit ? __('EditEquipment') : __('AddEquipment')" dialog-size="lg">
    <h2>{{ $isEdit ? __('EditEquipment') : __('AddEquipment') }}</h2>

    <form method="post" enctype="multipart/form-data" action="{{ $isEdit ? route('equipment.update', $equipment) : route('equipment.store') }}">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="code">{{ __('Code') }}</label>
                <input id="code" name="code" value="{{ $val('code') }}" class="form-control @error('code') is-invalid @enderror" required maxlength="50">
                @error('code')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="name">{{ __('Name') }}</label>
                <input id="name" name="name" value="{{ $val('name') }}" class="form-control @error('name') is-invalid @enderror" required maxlength="200">
                @error('name')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category">{{ __('Category') }}</label>
                <select id="category" name="category" class="form-select">
                    @foreach (\App\Enums\EquipmentCategory::cases() as $c)
                        <option value="{{ $c->value }}" @selected($selCategory === $c->value)>{{ $c->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="department_id">{{ __('Department') }}</label>
                <select id="department_id" name="department_id" class="form-select">
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((int) $val('department_id') === $d->id)>{{ $d->localized_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="location">{{ __('Location') }}</label>
                <input id="location" name="location" value="{{ $val('location') }}" class="form-control" maxlength="200">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select">
                    @foreach (\App\Enums\EquipmentStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($selStatus === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="manufacturer">{{ __('Manufacturer') }}</label>
                <input id="manufacturer" name="manufacturer" value="{{ $val('manufacturer') }}" class="form-control" maxlength="100">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="model">{{ __('Model') }}</label>
                <input id="model" name="model" value="{{ $val('model') }}" class="form-control" maxlength="100">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="serial_number">{{ __('SerialNumber') }}</label>
                <input id="serial_number" name="serial_number" value="{{ $val('serial_number') }}" class="form-control" maxlength="100">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="purchase_date">{{ __('PurchaseDate') }}</label>
                <input id="purchase_date" name="purchase_date" type="date" value="{{ $date('purchase_date') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="vendor">{{ __('Vendor') }}</label>
                <input id="vendor" name="vendor" value="{{ $val('vendor') }}" class="form-control" maxlength="200">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="purchase_price">{{ __('PurchasePrice') }}</label>
                <input id="purchase_price" name="purchase_price" type="number" step="0.01" min="0" value="{{ $val('purchase_price') }}" class="form-control">
            </div>
        </div>

        <h5 class="mt-4">{{ __('WarrantyInfo') }}</h5>
        <div class="form-check form-switch fs-5 mb-2">
            <input type="hidden" name="has_warranty" value="0">
            <input name="has_warranty" value="1" class="form-check-input" type="checkbox" role="switch" id="hasWarranty" @checked($hasWarranty)>
            <label class="form-check-label" for="hasWarranty">{{ __('HasWarranty') }}</label>
        </div>
        <div id="warrantyFields" class="row g-3 {{ $hasWarranty ? '' : 'd-none' }}">
            <div class="col-md-3">
                <label class="form-label" for="warranty_start">{{ __('WarrantyStart') }}</label>
                <input id="warranty_start" name="warranty_start" type="date" value="{{ $date('warranty_start') }}" class="form-control">
                @error('warranty_start')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="warranty_end">{{ __('WarrantyEnd') }} <span class="text-danger">*</span></label>
                <input id="warranty_end" name="warranty_end" type="date" value="{{ $date('warranty_end') }}" class="form-control @error('warranty_end') is-invalid @enderror">
                @error('warranty_end')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="warranty_provider">{{ __('WarrantyProvider') }}</label>
                <input id="warranty_provider" name="warranty_provider" value="{{ $val('warranty_provider') }}" class="form-control" maxlength="200">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="warranty_number">{{ __('WarrantyNumber') }}</label>
                <input id="warranty_number" name="warranty_number" value="{{ $val('warranty_number') }}" class="form-control" maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="warranty_doc">{{ __('WarrantyDocument') }}</label>
                <input id="warranty_doc" type="file" name="warranty_doc" class="form-control">
                @if ($equipment->warranty_document_url)
                    <a href="{{ $equipment->warranty_document_url }}" target="_blank" class="small">{{ __('View') }}</a>
                @endif
            </div>
        </div>

        <h5 class="mt-4">{{ __('FoodSafetySection') }} <small class="text-muted fw-normal">HACCP / FSSC 22000</small></h5>
        <div class="row g-3" id="foodSafetyFields" data-commissioned="{{ $commissioned ? 1 : 0 }}">
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="food_contact" name="food_contact" value="1" @checked($flag('food_contact')) data-fs-flag>
                    <label class="form-check-label" for="food_contact">{{ __('FoodContact') }}</label>
                </div>
                <div class="form-text">{{ __('FoodContactHint') }}</div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="is_critical" name="is_critical" value="1" @checked($flag('is_critical')) data-fs-flag>
                    <label class="form-check-label" for="is_critical">{{ __('CriticalEquipment') }}</label>
                </div>
                <div class="form-text">{{ __('CriticalEquipmentHint') }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ccp_reference">{{ __('CcpReference') }}</label>
                <input id="ccp_reference" name="ccp_reference" value="{{ $val('ccp_reference') }}" class="form-control" maxlength="100" placeholder="CCP-1 / oPRP-2" data-fs-flag>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="hygienic_design" name="hygienic_design" value="1" @checked($flag('hygienic_design'))>
                    <label class="form-check-label" for="hygienic_design">{{ __('HygienicDesign') }}</label>
                </div>
                <div class="form-text">{{ __('HygienicDesignHint') }}</div>
            </div>
            <div class="col-12">
                <div class="alert alert-warning small mb-0 d-none" id="commissioningHint">{{ __('CommissioningHint') }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="purchase_spec">{{ __('PurchaseSpec') }}</label>
                <input id="purchase_spec" type="file" name="purchase_spec" class="form-control">
                @error('purchase_spec')<span class="text-danger small">{{ $message }}</span>@enderror
                @if ($equipment->purchase_spec_url)
                    <a href="{{ $equipment->purchase_spec_url }}" target="_blank" class="small">{{ __('View') }}</a>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="conformity_doc">{{ __('ConformityDoc') }}</label>
                <input id="conformity_doc" type="file" name="conformity_doc" class="form-control">
                @error('conformity_doc')<span class="text-danger small">{{ $message }}</span>@enderror
                @if ($equipment->conformity_doc_url)
                    <a href="{{ $equipment->conformity_doc_url }}" target="_blank" class="small">{{ __('View') }}</a>
                @endif
            </div>
        </div>

        <h5 class="mt-4">{{ __('Calibration') }}</h5>
        <div class="form-check form-switch mb-2">
            <input type="checkbox" class="form-check-input" id="isMeasuring" name="is_measuring_device" value="1" @checked($isMeasuring)>
            <label class="form-check-label" for="isMeasuring">{{ __('MeasuringDevice') }}</label>
            <div class="form-text">{{ __('MeasuringDeviceHint') }}</div>
        </div>
        <div class="row g-3 {{ $isMeasuring ? '' : 'd-none' }}" id="calibrationFields">
            <div class="col-md-3">
                <label class="form-label" for="calibration_interval_days">{{ __('CalibrationIntervalDays') }}</label>
                <input id="calibration_interval_days" name="calibration_interval_days" type="number" min="1" max="3650" value="{{ $val('calibration_interval_days') }}" class="form-control @error('calibration_interval_days') is-invalid @enderror">
                @error('calibration_interval_days')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="last_calibration_date">{{ __('LastCalibration') }}</label>
                <input id="last_calibration_date" name="last_calibration_date" type="date" value="{{ $date('last_calibration_date') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="next_calibration_date">{{ __('NextCalibration') }}</label>
                <input id="next_calibration_date" name="next_calibration_date" type="date" value="{{ $date('next_calibration_date') }}" class="form-control">
                <div class="form-text">{{ __('NextCalibrationHint') }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="calibration_provider">{{ __('CalibrationProvider') }}</label>
                <input id="calibration_provider" name="calibration_provider" value="{{ $val('calibration_provider') }}" class="form-control" maxlength="200">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="calibration_certificate">{{ __('CalibrationCertificate') }}</label>
                <input id="calibration_certificate" type="file" name="calibration_certificate" class="form-control">
                @error('calibration_certificate')<span class="text-danger small">{{ $message }}</span>@enderror
                @if ($equipment->calibration_certificate_url)
                    <a href="{{ $equipment->calibration_certificate_url }}" target="_blank" class="small">{{ __('View') }}</a>
                @endif
            </div>
        </div>

        <h5 class="mt-4">{{ __('Attachments') }}</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="photo">{{ __('EquipmentPhoto') }}</label>
                <input id="photo" type="file" name="photo" class="form-control" accept="image/*">
                @error('photo')<span class="text-danger small">{{ $message }}</span>@enderror
                @if ($equipment->photo_url)
                    <a href="{{ $equipment->photo_url }}" target="_blank" class="small">{{ __('View') }}</a>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="manual">{{ __('Manual') }}</label>
                <input id="manual" type="file" name="manual" class="form-control">
                @if ($equipment->manual_file_url)
                    <a href="{{ $equipment->manual_file_url }}" target="_blank" class="small">{{ __('View') }}</a>
                @endif
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">{{ __('Notes') }}</label>
                <textarea id="notes" name="notes" class="form-control" rows="2">{{ $val('notes') }}</textarea>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $isEdit ? route('equipment.show', $equipment) : route('equipment.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>

    <x-slot:scripts>
        <script>
            (function () {
                const toggle = document.getElementById('hasWarranty');
                const fields = document.getElementById('warrantyFields');
                toggle.addEventListener('change', () => fields.classList.toggle('d-none', !toggle.checked));

                const measuring = document.getElementById('isMeasuring');
                const calibration = document.getElementById('calibrationFields');
                measuring.addEventListener('change', () => calibration.classList.toggle('d-none', !measuring.checked));

                const fs = document.getElementById('foodSafetyFields');
                const status = document.getElementById('status');
                const working = status.querySelector('option[value="Working"]');
                const hint = document.getElementById('commissioningHint');
                const commissioned = fs.dataset.commissioned === '1';
                function refreshCommissioning() {
                    const relevant = Array.from(fs.querySelectorAll('[data-fs-flag]')).some(el => el.type === 'checkbox' ? el.checked : el.value.trim() !== '');
                    const locked = relevant && !commissioned;
                    hint.classList.toggle('d-none', !locked);
                    working.disabled = locked;
                    if (locked && status.value === 'Working') { status.value = 'OutOfService'; }
                }
                fs.querySelectorAll('[data-fs-flag]').forEach(el => el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', refreshCommissioning));
                refreshCommissioning();
            })();
        </script>
    </x-slot:scripts>
</x-layouts.app>
