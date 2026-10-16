@php $isEdit = $group->exists; @endphp
<x-layouts.app :title="$isEdit ? __('EditGroup') : __('AddGroup')">
    <h2>{{ $isEdit ? __('EditGroup') : __('AddGroup') }}</h2>
    <form method="post" action="{{ $isEdit ? route('groups.update', $group) : route('groups.store') }}">
        @csrf
        @if ($isEdit) @method('put') @endif
        <div class="row">
            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label" for="name_ar">{{ __('GroupNameAr') }}</label>
                    <input id="name_ar" name="name_ar" value="{{ old('name_ar', $group->name_ar) }}" class="form-control" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="name_en">{{ __('GroupNameEn') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en', $group->name_en) }}" class="form-control" required maxlength="100" dir="ltr">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">{{ __('Description') }}</label>
                    <textarea id="description" name="description" class="form-control" rows="3" maxlength="500">{{ old('description', $group->description) }}</textarea>
                </div>
                @if ($group->default_for_role)
                    <div class="alert alert-light border small">{{ __('DefaultGroupHint', ['role' => $group->default_for_role->label()]) }}</div>
                @endif
            </div>
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">{{ __('Permissions') }}</label>
                    <div>
                        <button type="button" class="btn btn-sm btn-link" data-perm-all="1">{{ __('SelectAll') }}</button>
                        <button type="button" class="btn btn-sm btn-link" data-perm-all="0">{{ __('ClearAll') }}</button>
                    </div>
                </div>
                <div class="row g-3" id="permissionMatrix">
                    @foreach ($modules as $module => $perms)
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header py-2"><strong>{{ __('Module_'.$module) }}</strong></div>
                                <div class="card-body py-2">
                                    @foreach ($perms as $p)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" id="perm_{{ str_replace('.', '_', $p->value) }}" value="{{ $p->value }}" @checked(in_array($p->value, $selected, true))>
                                            <label class="form-check-label" for="perm_{{ str_replace('.', '_', $p->value) }}">{{ $p->label() }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('groups.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
    <script>
        document.querySelectorAll('[data-perm-all]').forEach(btn => btn.addEventListener('click', () => {
            const on = btn.dataset.permAll === '1';
            document.querySelectorAll('#permissionMatrix input[type=checkbox]').forEach(cb => cb.checked = on);
        }));
    </script>
</x-layouts.app>
