@php $isPriority = $prefix === 'priorities'; @endphp
<x-layouts.app :title="__($titleKey)">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __($titleKey) }}</h2>
        <a class="btn btn-primary" href="{{ route($prefix.'.create') }}">+ {{ __('Add') }}</a>
    </div>
    <p class="text-muted small">{{ __($titleKey.'_Hint') }}</p>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    @if ($isPriority)<th></th>@endif
                    <th>{{ __('NameAr') }}</th><th>{{ __('NameEn') }}</th>
                    @if ($isPriority)
                        <th>{{ __('Rank') }}</th><th>{{ __('Options') }}</th>
                    @else
                        <th>{{ __('SortOrder') }}</th>
                    @endif
                    <th>{{ __('Requests') }}</th><th>{{ __('Status') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        @if ($isPriority)
                            <td><x-status-badge :status="$item" /></td>
                        @endif
                        <td dir="rtl">{{ $item->name_ar }}@if ($isPriority && $item->hint_ar) <div class="small text-muted">{{ $item->hint_ar }}</div>@endif</td>
                        <td dir="ltr">{{ $item->name_en }}@if ($isPriority && $item->hint_en) <div class="small text-muted">{{ $item->hint_en }}</div>@endif</td>
                        @if ($isPriority)
                            <td>{{ $item->rank }}</td>
                            <td class="small">
                                @if ($item->is_default)<span class="badge bg-primary">{{ __('Default') }}</span>@endif
                                @if ($item->is_critical)<span class="badge bg-danger">{{ __('CriticalFlag') }}</span>@endif
                                @if ($item->show_in_quick)<span class="badge bg-secondary">{{ __('ShowInQuick') }}</span>@endif
                            </td>
                        @else
                            <td>{{ $item->sort_order }}</td>
                        @endif
                        <td>{{ $item->requests_count }}</td>
                        <td><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route($prefix.'.edit', $item) }}">{{ __('Edit') }}</a>
                            @if ($item->requests_count === 0)
                                <form action="{{ route($prefix.'.destroy', $item) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                                    @csrf @method('delete')
                                    <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
