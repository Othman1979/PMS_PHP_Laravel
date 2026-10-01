@php $isEdit = $department->exists; @endphp
<x-layouts.app :title="$isEdit ? __('EditDepartment') : __('AddDepartment')">
    <h2>{{ $isEdit ? __('EditDepartment') : __('AddDepartment') }}</h2>
    <div class="row">
        <div class="col-lg-5">
            <form method="post" action="{{ $isEdit ? route('departments.update', $department) : route('departments.store') }}">
                @csrf
                @if ($isEdit) @method('put') @endif
                <div class="mb-3">
                    <label class="form-label" for="name_en">{{ __('DepartmentNameEn') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en', $department->name_en) }}" class="form-control" dir="ltr" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="name_ar">{{ __('DepartmentNameAr') }}</label>
                    <input id="name_ar" name="name_ar" value="{{ old('name_ar', $department->name_ar) }}" class="form-control" dir="rtl" required maxlength="100">
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $department->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-layouts.app>
