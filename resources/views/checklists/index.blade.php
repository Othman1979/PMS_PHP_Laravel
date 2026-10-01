<x-layouts.app :title="__('ManageChecklists')">
    <h2>{{ __('ManageChecklists') }}</h2>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Items') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($checklists as $c)
                            <tr>
                                <td>{{ $c->localized_name }}</td>
                                <td>{{ $c->category?->label() ?? '-' }}</td>
                                <td>{{ $c->items_count }}</td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('checklists.edit', $c) }}">{{ __('Edit') }}</a>
                                    <form action="{{ route('checklists.destroy', $c) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ __('AddChecklist') }}</strong></div>
                <div class="card-body">
                    <form action="{{ route('checklists.store') }}" method="post">
                        @csrf
                        <div class="mb-2"><input name="name_en" class="form-control" dir="ltr" placeholder="{{ __('ChecklistNameEn') }}" required maxlength="200"></div>
                        <div class="mb-2"><input name="name_ar" class="form-control" dir="rtl" placeholder="{{ __('ChecklistNameAr') }}" required maxlength="200"></div>
                        <div class="mb-3">
                            <select name="category" class="form-select">
                                <option value="">{{ __('Category') }}</option>
                                @foreach (\App\Enums\EquipmentCategory::cases() as $cat)
                                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary">{{ __('Create') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
