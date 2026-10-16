<x-layouts.app :title="__('ManageUsers')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('ManageUsers') }}</h2>
        <div>
            <a class="btn btn-outline-secondary" href="{{ route('groups.index') }}">{{ __('UserGroups') }}</a>
            <a class="btn btn-primary" href="{{ route('users.create') }}">+ {{ __('AddUser') }}</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>{{ __('FullName') }}</th><th>{{ __('UserName') }}</th><th>{{ __('Department') }}</th><th>{{ __('Role') }}</th><th>{{ __('UserGroups') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td>{{ $u->full_name }}</td>
                        <td class="text-nowrap">{{ $u->username }}</td>
                        <td>{{ $u->department?->localized_name }}</td>
                        <td class="text-nowrap">
                            <span class="badge bg-secondary">{{ $u->role->label() }}</span>
                            @if ($u->specialtyLabel())
                                <span class="badge bg-info text-dark">{{ $u->specialtyLabel() }}</span>
                            @endif
                        </td>
                        <td>
                            @foreach ($u->groups as $g)
                                <span class="badge bg-primary-subtle text-primary-emphasis border">{{ $g->localized_name }}</span>
                            @endforeach
                        </td>
                        <td class="text-nowrap"><span class="badge {{ $u->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $u->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td><a class="btn btn-sm btn-outline-secondary" href="{{ route('users.edit', $u) }}">{{ __('Edit') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
