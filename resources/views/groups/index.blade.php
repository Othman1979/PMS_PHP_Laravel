<x-layouts.app :title="__('UserGroups')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('UserGroups') }}</h2>
        <div>
            <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">{{ __('ManageUsers') }}</a>
            <a class="btn btn-primary" href="{{ route('groups.create') }}">+ {{ __('AddGroup') }}</a>
        </div>
    </div>
    <p class="text-muted small">{{ __('UserGroupsIntro') }}</p>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>{{ __('GroupName') }}</th><th class="d-none d-md-table-cell">{{ __('Permissions') }}</th><th>{{ __('Members') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($groups as $g)
                    <tr>
                        <td>
                            <strong>{{ $g->localized_name }}</strong>
                            @if ($g->default_for_role)
                                <span class="badge bg-light text-dark border">{{ __('DefaultFor') }}: {{ $g->default_for_role->label() }}</span>
                            @endif
                            @if ($g->description)<div class="small text-muted">{{ $g->description }}</div>@endif
                            <div class="small text-muted d-md-none">{{ $g->permissions->count() }} {{ __('Permissions') }}</div>
                        </td>
                        <td class="small d-none d-md-table-cell">
                            @php $perms = $g->permissions->pluck('permission')->map(fn ($p) => \App\Enums\Permission::from($p)->label()); @endphp
                            @if ($perms->count() === count(\App\Enums\Permission::cases()))
                                <span class="badge bg-success">{{ __('AllPermissions') }}</span>
                            @elseif ($perms->isEmpty())
                                <span class="text-muted">{{ __('NoPermissions') }}</span>
                            @else
                                {{ $perms->take(6)->implode('، ') }}@if ($perms->count() > 6) <span class="text-muted">+{{ $perms->count() - 6 }}</span>@endif
                            @endif
                        </td>
                        <td>{{ $g->users_count }}</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('groups.edit', $g) }}">{{ __('Edit') }}</a>
                            @if ($g->default_for_role === null)
                                <form action="{{ route('groups.destroy', $g) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                                    @csrf @method('delete')
                                    <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
