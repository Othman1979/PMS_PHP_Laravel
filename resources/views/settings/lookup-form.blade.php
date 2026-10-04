@php
    $isEdit = $item->exists;
    $isPriority = $prefix === 'priorities';
    $title = ($isEdit ? __('Edit') : __('Add')).' — '.__($titleKey);
@endphp
<x-layouts.app :title="$title">
    <h2>{{ $title }}</h2>
    <div class="row">
        <div class="col-lg-6">
            <form method="post" action="{{ $isEdit ? route($prefix.'.update', $item) : route($prefix.'.store') }}">
                @csrf
                @if ($isEdit) @method('put') @endif
                <div class="mb-3">
                    <label class="form-label" for="name_ar">{{ __('NameAr') }}</label>
                    <input id="name_ar" name="name_ar" value="{{ old('name_ar', $item->name_ar) }}" class="form-control" dir="rtl" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="name_en">{{ __('NameEn') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en', $item->name_en) }}" class="form-control" dir="ltr" required maxlength="100">
                </div>
                @if ($isPriority)
                    <div class="mb-3">
                        <label class="form-label" for="hint_ar">{{ __('HintAr') }}</label>
                        <input id="hint_ar" name="hint_ar" value="{{ old('hint_ar', $item->hint_ar) }}" class="form-control" dir="rtl" maxlength="120" placeholder="{{ __('PriorityHintPlaceholder') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="hint_en">{{ __('HintEn') }}</label>
                        <input id="hint_en" name="hint_en" value="{{ old('hint_en', $item->hint_en) }}" class="form-control" dir="ltr" maxlength="120">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="color">{{ __('Color') }}</label>
                            <input id="color" name="color" type="color" value="{{ old('color', $item->color) }}" class="form-control form-control-color w-100">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="rank">{{ __('Rank') }}</label>
                            <input id="rank" name="rank" type="number" min="0" max="99" value="{{ old('rank', $item->rank) }}" class="form-control" required>
                            <div class="form-text">{{ __('RankHint') }}</div>
                        </div>
                    </div>
                    @foreach (['is_default' => 'Default', 'is_critical' => 'CriticalFlag', 'show_in_quick' => 'ShowInQuick'] as $flag => $label)
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="{{ $flag }}" value="0">
                            <input class="form-check-input" type="checkbox" id="{{ $flag }}" name="{{ $flag }}" value="1" @checked(old($flag, $item->{$flag}))>
                            <label class="form-check-label" for="{{ $flag }}">{{ __($label) }} <span class="text-muted small">{{ __($label.'_Hint') }}</span></label>
                        </div>
                    @endforeach
                @else
                    <div class="mb-3">
                        <label class="form-label" for="sort_order">{{ __('SortOrder') }}</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $item->sort_order ?? 0) }}" class="form-control">
                    </div>
                @endif
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route($prefix.'.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-layouts.app>
