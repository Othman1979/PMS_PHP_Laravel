<x-layouts.app :title="__('ManageDepartments')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('ManageDepartments') }}</h2>
        <a class="btn btn-primary" href="{{ route('departments.create') }}">+ {{ __('AddDepartment') }}</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>{{ __('DepartmentNameEn') }}</th><th>{{ __('DepartmentNameAr') }}</th><th>{{ __('Users') }}</th><th>{{ __('Equipment') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($departments as $d)
                    <tr>
                        <td dir="ltr">{{ $d->name_en }}</td>
                        <td dir="rtl">{{ $d->name_ar }}</td>
                        <td>{{ $d->users_count }}</td>
                        <td>{{ $d->equipment_count }}</td>
                        <td><span class="badge {{ $d->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $d->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('departments.edit', $d) }}">{{ __('Edit') }}</a>
                            <form action="{{ route('departments.destroy', $d) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                                @csrf @method('delete')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
