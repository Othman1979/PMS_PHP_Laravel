@php
    $isEdit = $equipment->exists;
    $hasWarranty = (bool) old('has_warranty', $equipment->has_warranty);
    $val = fn (string $f) => old($f, $equipment->{$f});
    $date = fn (string $f) => old($f, $equipment->{$f}?->format('Y-m-d'));
    $selCategory = old('category', $equipment->category?->value);
    $selStatus = old('status', $equipment->status?->value);
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
            })();
        </script>
    </x-slot:scripts>
</x-layouts.app>
