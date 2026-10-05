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
                            <option value="{{ $e->id }}" data-department="{{ $e->department_id }}" data-food="{{ $e->isFoodSafetyRelevant() ? 1 : 0 }}" @selected((int) old('equipment_id', $selectedEquipment) === $e->id)>{{ $e->code }} - {{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3 form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="food_safety_impact" value="1" id="food_safety_impact" @checked(old('food_safety_impact'))>
                    <label class="form-check-label fw-semibold" for="food_safety_impact">{{ __('FoodSafetyImpactQuestion') }}</label>
                    <div class="form-text">{{ __('FoodSafetyImpactHint') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">{{ __('Description') }}</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required maxlength="2000">{{ old('description') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="priority_id">{{ __('Priority') }}</label>
                    <select id="priority_id" name="priority_id" class="form-select">
                        @foreach ($priorities as $p)
                            <option value="{{ $p->id }}" @selected((int) old('priority_id', $defaultPriority->id) === $p->id)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($faultTypes->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label" for="fault_type_id">{{ __('FaultType') }} {{ __('Optional') }}</label>
                        <select id="fault_type_id" name="fault_type_id" class="form-select">
                            <option value="">—</option>
                            @foreach ($faultTypes as $t)
                                <option value="{{ $t->id }}" @selected((int) old('fault_type_id') === $t->id)>{{ $t->localized_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
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
