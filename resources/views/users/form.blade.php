@php $isEdit = $user->exists; @endphp
<x-layouts.app :title="$isEdit ? __('EditUser') : __('AddUser')">
    <h2>{{ $isEdit ? __('EditUser') : __('AddUser') }}</h2>
    <div class="row">
        <div class="col-lg-5">
            <form method="post" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}">
                @csrf
                @if ($isEdit) @method('put') @endif
                <div class="mb-3">
                    <label class="form-label" for="full_name">{{ __('FullName') }}</label>
                    <input id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" class="form-control" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="username">{{ __('UserName') }}</label>
                    <input id="username" name="username" value="{{ old('username', $user->username) }}" class="form-control" required autocomplete="off" autocapitalize="none" dir="ltr">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="phone">{{ __('Phone') }}</label>
                    <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" dir="ltr">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="department_id">{{ __('Department') }}</label>
                    <select id="department_id" name="department_id" class="form-select">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->localized_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="role">{{ __('Role') }}</label>
                    <select id="role" name="role" class="form-select">
                        @foreach (\App\Enums\Role::cases() as $r)
                            <option value="{{ $r->value }}" @selected(old('role', $user->role?->value) === $r->value)>{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="specialty">{{ __('Specialty') }} <small class="text-muted">({{ \App\Enums\Role::Technician->label() }})</small></label>
                    <select id="specialty" name="specialty" class="form-select">
                        <option value="">{{ __('None') }}</option>
                        @foreach (\App\Enums\EquipmentCategory::cases() as $c)
                            <option value="{{ $c->value }}" @selected(old('specialty', $user->specialty?->value) === $c->value)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">{{ $isEdit ? __('NewPassword') : __('Password') }}</label>
                    <input id="password" name="password" type="password" class="form-control" autocomplete="new-password" @required(! $isEdit) minlength="4">
                    @if ($isEdit)<div class="form-text">{{ __('NewPasswordHint') }}</div>@endif
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-layouts.app>
