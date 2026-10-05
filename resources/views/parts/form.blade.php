@php
    $isEdit = $part->exists;
    $val = fn (string $f) => old($f, $part->{$f});
@endphp
<x-layouts.app :title="$isEdit ? __('EditSparePart') : __('AddSparePart')">
    <h2>{{ $isEdit ? __('EditSparePart') : __('AddSparePart') }}</h2>
    <div class="row">
        <div class="col-lg-5">
            <form method="post" action="{{ $isEdit ? route('parts.update', $part) : route('parts.store') }}" enctype="multipart/form-data">
                @csrf
                @if ($isEdit) @method('PUT') @endif
                <div class="mb-3">
                    <label class="form-label" for="name">{{ __('Name') }}</label>
                    <input id="name" name="name" value="{{ $val('name') }}" class="form-control" required maxlength="200">
                    @error('name')<span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-4">
                        <label class="form-label" for="part_number">{{ __('PartNumber') }}</label>
                        <input id="part_number" name="part_number" value="{{ $val('part_number') }}" class="form-control" maxlength="100">
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="manufacturer">{{ __('Manufacturer') }}</label>
                        <input id="manufacturer" name="manufacturer" value="{{ $val('manufacturer') }}" class="form-control" maxlength="200">
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label" for="unit">{{ __('Unit') }}</label>
                        <input id="unit" name="unit" value="{{ $val('unit') }}" class="form-control" placeholder="{{ __('UnitPlaceholder') }}" maxlength="30">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="quantity">{{ __('Quantity') }}</label>
                    <input id="quantity" name="quantity" type="number" min="0" value="{{ $val('quantity') }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="minimum_quantity">{{ __('MinimumQuantity') }}</label>
                    <input id="minimum_quantity" name="minimum_quantity" type="number" min="0" value="{{ $val('minimum_quantity') }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="unit_cost">{{ __('UnitCost') }}</label>
                    <input id="unit_cost" name="unit_cost" type="number" step="0.01" min="0" value="{{ $val('unit_cost') }}" class="form-control" required>
                </div>
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_food_grade" value="1" id="is_food_grade" @checked(old('is_food_grade', $part->is_food_grade))>
                        <label class="form-check-label fw-semibold" for="is_food_grade">{{ __('FoodGrade') }}</label>
                        <div class="form-text">{{ __('FoodGradeHint') }}</div>
                    </div>
                    <label class="form-label" for="food_grade_certificate">{{ __('FoodGradeCertificate') }}</label>
                    <input id="food_grade_certificate" name="food_grade_certificate" type="file" class="form-control" accept=".pdf,image/*">
                    @error('food_grade_certificate')<span class="text-danger small">{{ $message }}</span>@enderror
                    @if ($part->food_grade_certificate_url)
                        <a href="{{ $part->food_grade_certificate_url }}" target="_blank" class="small d-block mt-1">{{ __('ViewDocument') }}</a>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('parts.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-layouts.app>
