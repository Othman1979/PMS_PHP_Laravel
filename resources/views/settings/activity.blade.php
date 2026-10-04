<x-layouts.app :title="__('ActivityLog')">
    <h2 class="mb-3">{{ __('ActivityLog') }}</h2>
    <form method="get" class="row g-2 mb-3 align-items-center">
        <div class="col-6 col-md-auto">
            <select name="user_id" class="form-select">
                <option value="">{{ __('Who') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <select name="action" class="form-select">
                <option value="">{{ __('Action') }}</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}" @selected(($filters['action'] ?? null) === $a)>{{ __('Activity_'.$a) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
        <div class="col-6 col-md-auto"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            <a class="btn btn-link btn-sm" href="{{ route('settings.activity') }}">{{ __('ResetFilters') }}</a>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle table-cards">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Details') }}</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td data-label="{{ __('When') }}" class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td data-label="{{ __('Who') }}">{{ $log->user?->full_name ?? __('System') }}</td>
                        <td data-label="{{ __('Action') }}"><span class="badge text-bg-light">{{ $log->actionLabel() }}</span></td>
                        <td data-label="{{ __('Details') }}">
                            @if ($url = $log->subjectUrl())<a href="{{ $url }}">#{{ $log->subject_id }}</a> @endif{{ $log->description }}
                        </td>
                        <td data-label="IP" dir="ltr" class="small text-muted">{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-layouts.app>
