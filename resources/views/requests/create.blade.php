<x-layouts.app :title="__('NewMaintenanceRequest')">
    <h2>{{ __('NewMaintenanceRequest') }}</h2>
    <div class="row">
        <div class="col-lg-6">
            <form method="post" action="{{ route('requests.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="department_id">{{ __('Department') }}</label>
                    <select id="department_id" name="department_id" class="form-select" required>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected((int) old('department_id', $defaultDepartment) === $d->id)>{{ $d->localized_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="equipment_id">{{ __('Equipment') }} {{ __('Optional') }}</label>
                    <select id="equipment_id" name="equipment_id" class="form-select">
                        <option value="">{{ __('OtherNoEquipment') }}</option>
                        @foreach ($equipment as $e)
                            <option value="{{ $e->id }}" data-department="{{ $e->department_id }}" @selected((int) old('equipment_id', $selectedEquipment) === $e->id)>{{ $e->code }} - {{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">{{ __('Description') }}</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required maxlength="2000">{{ old('description') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="priority">{{ __('Priority') }}</label>
                    <select id="priority" name="priority" class="form-select">
                        @foreach (\App\Enums\RequestPriority::cases() as $p)
                            <option value="{{ $p->value }}" @selected(old('priority', 'Normal') === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="files">{{ __('AttachFiles') }} {{ __('Optional') }}</label>
                    <input id="files" name="files[]" type="file" class="form-control" multiple accept="image/*,.pdf">
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
                <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-layouts.app>
