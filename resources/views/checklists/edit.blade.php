<x-layouts.app :title="__('Edit').' — '.$checklist->localized_name">
    <h2>{{ $checklist->localized_name }}</h2>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><strong>{{ __('Edit') }}</strong></div>
                <div class="card-body">
                    <form action="{{ route('checklists.update', $checklist) }}" method="post">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <label class="form-label" for="name_en">{{ __('ChecklistNameEn') }}</label>
                            <input id="name_en" name="name_en" value="{{ old('name_en', $checklist->name_en) }}" class="form-control" dir="ltr" required maxlength="200">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="name_ar">{{ __('ChecklistNameAr') }}</label>
                            <input id="name_ar" name="name_ar" value="{{ old('name_ar', $checklist->name_ar) }}" class="form-control" dir="rtl" required maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="category">{{ __('Category') }}</label>
                            <select id="category" name="category" class="form-select">
                                <option value="">—</option>
                                @foreach (\App\Enums\EquipmentCategory::cases() as $cat)
                                    <option value="{{ $cat->value }}" @selected($checklist->category === $cat)>{{ $cat->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary">{{ __('Save') }}</button>
                        <a href="{{ route('checklists.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><strong>{{ __('Items') }}</strong></div>
                <ul class="list-group list-group-flush">
                    @foreach ($checklist->items as $item)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $item->sort_order }}. {{ $item->localized_text }} <small class="text-muted">({{ $item->text_en }})</small></span>
                            <form action="{{ route('checklists.items.destroy', [$checklist, $item]) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <div class="card-body border-top">
                    <form action="{{ route('checklists.items.store', $checklist) }}" method="post" class="row g-2">
                        @csrf
                        <div class="col-md-6"><input name="text_en" class="form-control form-control-sm" dir="ltr" placeholder="{{ __('ItemTextEn') }}" required maxlength="500"></div>
                        <div class="col-md-5"><input name="text_ar" class="form-control form-control-sm" dir="rtl" placeholder="{{ __('ItemTextAr') }}" required maxlength="500"></div>
                        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">+</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
