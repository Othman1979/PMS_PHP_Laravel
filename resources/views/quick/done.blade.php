<x-layouts.quick :title="__('Quick_Sent')">
    <div class="quick-done">
        <span class="quick-done-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </span>
        <h1>{{ __('Quick_Sent') }}</h1>
        <div class="quick-done-number">{{ $mr->request_number }}</div>
        <div class="mb-2">
            <x-status-badge :status="$mr->status" />
            <x-status-badge :status="$mr->priority" />
        </div>
        @if ($mr->equipment)
            <div class="fw-bold">{{ $mr->equipment->name }}</div>
        @endif
        <p class="text-muted mt-2">{{ __('Quick_SentHint') }}</p>
        <div class="d-grid gap-2 mt-3">
            <a class="btn btn-primary quick-send" href="{{ route('requests.show', $mr) }}">{{ __('Quick_Track') }}</a>
            @if ($mr->equipment)
                <a class="btn btn-outline-secondary btn-lg" href="{{ route('quick.show', $mr->equipment->code) }}">{{ __('Quick_Another') }}</a>
            @endif
            <a class="btn btn-link" href="{{ route('requests.index') }}">{{ __('MyRequests') }}</a>
        </div>
    </div>
</x-layouts.quick>
