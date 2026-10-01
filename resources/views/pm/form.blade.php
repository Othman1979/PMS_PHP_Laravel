@php
    $isEdit = $plan->exists;
    $val = fn (string $f) => old($f, $plan->{$f});
@endphp
<x-layouts.app :title="$isEdit ? __('EditPlan') : __('AddPlan')">
    <h2>{{ $isEdit ? __('EditPlan') : __('AddPlan') }}</h2>
    <div class="row">
        <div class="col-lg-6">
            <form method="post" action="{{ $isEdit ? route('pm.update', $plan) : route('pm.store') }}">
                @csrf
                @if ($isEdit) @method('PUT') @endif
                <div class="mb-3">
                    <label class="form-label" for="equipment_id">{{ __('Equipment') }}</label>
                    <select id="equipment_id" name="equipment_id" class="form-select" required>
                        @foreach ($equipment as $e)
                            <option value="{{ $e->id }}" @selected((int) $val('equipment_id') === $e->id)>{{ $e->code }} - {{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="strategy">{{ __('Strategy') }}</label>
                    <select id="strategy" name="strategy" class="form-select">
                        @foreach (\App\Enums\PmStrategy::cases() as $s)
                            <option value="{{ $s->value }}" @selected(old('strategy', $plan->strategy?->value) === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="task_description_en">{{ __('TaskDescriptionEn') }}</label>
                    <input id="task_description_en" name="task_description_en" value="{{ $val('task_description_en') }}" class="form-control" dir="ltr" required maxlength="500">
                    @error('task_description_en')<span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="task_description_ar">{{ __('TaskDescriptionAr') }}</label>
                    <input id="task_description_ar" name="task_description_ar" value="{{ $val('task_description_ar') }}" class="form-control" dir="rtl" required maxlength="500">
                    @error('task_description_ar')<span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="frequency_days">{{ __('FrequencyDays') }}</label>
                    <input id="frequency_days" name="frequency_days" type="number" min="1" value="{{ $val('frequency_days') }}" class="form-control" required>
                    @error('frequency_days')<span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="next_due_date">{{ __('NextDueDate') }}</label>
                    <input id="next_due_date" name="next_due_date" type="date" value="{{ old('next_due_date', $plan->next_due_date?->format('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="checklist_id">{{ __('LinkedChecklist') }}</label>
                    <select id="checklist_id" name="checklist_id" class="form-select">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($checklists as $c)
                            <option value="{{ $c->id }}" @selected((int) $val('checklist_id') === $c->id)>{{ $c->localized_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input id="is_active" name="is_active" value="1" type="checkbox" class="form-check-input" @checked(old('is_active', $plan->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('IsActive') }}</label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('pm.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-layouts.app>
