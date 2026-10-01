<x-layouts.app :title="__('ManageUsers')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('ManageUsers') }}</h2>
        <a class="btn btn-primary" href="{{ route('users.create') }}">+ {{ __('AddUser') }}</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>{{ __('FullName') }}</th><th>{{ __('UserName') }}</th><th>{{ __('Department') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td>{{ $u->full_name }}</td>
                        <td>{{ $u->username }}</td>
                        <td>{{ $u->department?->localized_name }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $u->role->label() }}</span>
                            @if ($u->specialty)
                                <span class="badge bg-info text-dark">{{ $u->specialty->label() }}</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $u->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $u->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td><a class="btn btn-sm btn-outline-secondary" href="{{ route('users.edit', $u) }}">{{ __('Edit') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
