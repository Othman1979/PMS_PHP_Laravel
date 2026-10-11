<x-layouts.quick :title="__('GeneralFault')">
    <h1 class="quick-step-title">{{ __('GeneralFault') }}</h1>
    <p class="text-muted">{{ __('GeneralFaultHint') }}</p>
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="post" action="{{ route('quick.general.store') }}" enctype="multipart/form-data">
        @csrf
        <section class="quick-step">
            <label class="form-label" for="department_id">{{ __('Department') }}</label>
            <select class="form-select mb-3" id="department_id" name="department_id" required>
                <option value="">{{ __('Department') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) old('department_id', $defaultDepartment) === $department->id)>{{ $department->localized_name }}</option>
                @endforeach
            </select>
            <label class="form-label" for="location">{{ __('FaultLocation') }}</label>
            <input class="form-control mb-3" id="location" name="location" value="{{ old('location') }}" required maxlength="255" placeholder="{{ __('FaultLocationHint') }}">
            <label class="form-label" for="description">{{ __('Description') }}</label>
            <textarea class="form-control quick-text" id="description" name="description" rows="3" required maxlength="2000">{{ old('description') }}</textarea>
        </section>
        <section class="quick-step">
            <h2 class="quick-step-title">{{ __('Priority') }}</h2>
            <div class="quick-prios">
                @foreach ($priorities as $priority)
                    <label class="quick-prio" style="--prio-color: {{ $priority->color }}">
                        <input type="radio" name="priority_id" value="{{ $priority->id }}" @checked((int) old('priority_id', $defaultPriority->id) === $priority->id)>
                        <span class="quick-prio-dot"></span>
                        <span class="quick-prio-title">{{ $priority->label() }}</span>
                    </label>
                @endforeach
            </div>
            <label class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="food_safety_impact" value="1" @checked(old('food_safety_impact'))>
                <span class="form-check-label">{{ __('FoodSafetyImpactQuestion') }}</span>
            </label>
        </section>
        <section class="quick-step">
            <label class="form-label" for="photo">{{ __('AttachFiles') }} — {{ __('Optional') }}</label>
            <input class="form-control" type="file" id="photo" name="photo" accept="image/*">
        </section>
        <button class="btn btn-primary quick-send w-100" type="submit">{{ __('Submit') }}</button>
        <a class="btn btn-link w-100 mt-2" href="{{ route('quick.find') }}">{{ __('Back') }}</a>
    </form>
</x-layouts.quick>
