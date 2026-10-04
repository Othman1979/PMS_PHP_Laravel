<x-layouts.quick :title="__('MyRequests')">
    <h2 class="quick-step-title">{{ __('MyRequests') }}</h2>
    @if ($requests->isEmpty())
        <div class="text-muted">{{ __('Quick_NoRequests') }}</div>
    @else
        <div class="quick-list mb-3" data-live-reload="user.{{ auth()->id() }}" data-live-self="*">
            @foreach ($requests as $r)
                <a class="quick-list-item" href="{{ route('requests.show', $r) }}">
                    <span class="quick-list-name">{{ $r->equipment?->name }} <small class="text-muted">{{ $r->request_number }}</small></span>
                    <span class="quick-list-meta d-flex align-items-center gap-2 flex-wrap">
                        <x-status-badge :status="$r->status" />
                        @if ($r->status === \App\Enums\RequestStatus::Completed)<strong class="text-success">{{ __('Quick_ConfirmNeeded') }}</strong>@endif
                        @if ($r->assignedTechnician)<span>{{ $r->assignedTechnician->full_name }}</span>@endif
                        <span>{{ $r->created_at->format('Y-m-d H:i') }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        {{ $requests->links() }}
    @endif
    <a href="{{ route('quick.find') }}" class="btn btn-outline-secondary w-100">{{ __('QuickRequest') }}</a>
</x-layouts.quick>
