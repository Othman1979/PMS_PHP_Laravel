<x-layouts.app :title="__('MyTasks')">
    <h2 class="h4 mb-3" data-live-reload="technician.{{ auth()->id() }}">{{ __('MyTasks') }} <span class="badge bg-primary">{{ $tasks->count() }}</span></h2>

    @if ($tasks->isEmpty())
        <div class="alert alert-light border">{{ __('NoTasks') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ($tasks as $r)
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('requests.show', $r) }}" class="card h-100 text-decoration-none text-reset shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <strong>{{ $r->request_number }}</strong>
                            <x-status-badge :status="$r->priority" />
                        </div>
                        <div class="mb-2"><x-status-badge :status="$r->status" /></div>
                        <div class="fw-semibold">{{ $r->equipment?->name ?? $r->department?->localized_name }}</div>
                        <div class="small text-muted mb-2">{{ $r->department?->localized_name }}</div>
                        <p class="mb-2">{{ \Illuminate\Support\Str::limit($r->description, 140) }}</p>
                        <small class="text-muted">{{ __('AssignedTo') }}: {{ $r->assigned_at?->format('Y-m-d H:i') }}</small>
                    </div>
                    <div class="card-footer bg-transparent">
                        <span class="btn btn-primary w-100">{{ __('OpenTask') }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if ($recentDone->isNotEmpty())
        <h3 class="h6 text-muted">{{ __('RecentlyCompleted') }}</h3>
        <div class="list-group">
            @foreach ($recentDone as $r)
                <a href="{{ route('requests.show', $r) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span>{{ $r->request_number }} — {{ $r->equipment?->name ?? $r->department?->localized_name }}</span>
                    <x-status-badge :status="$r->status" />
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
