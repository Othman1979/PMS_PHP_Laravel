<x-layouts.quick :title="__('QuickRequest')">
    @if ($notFound)
        <div class="alert alert-danger fw-bold">{{ __('Quick_NotFound') }}: {{ $q }}</div>
    @endif

    <p class="quick-lead">{{ __('Quick_FindTitle') }}</p>

    <form method="get" action="{{ route('quick.find') }}" class="quick-search mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control quick-text" placeholder="{{ __('Quick_FindPlaceholder') }}" autocomplete="off">
        <button class="btn btn-primary">{{ __('Search') }}</button>
    </form>

    @if ($results->isNotEmpty())
        @if ($q === '')
            <h2 class="quick-step-title">{{ __('Quick_MyDeptEquipment') }}</h2>
        @endif
        <div class="quick-list">
            @foreach ($results as $e)
                <a class="quick-list-item" href="{{ route('quick.show', $e->code) }}">
                    <span class="quick-list-name">{{ $e->name }}</span>
                    <span class="quick-list-meta">{{ $e->code }}{{ $e->location ? ' · '.$e->location : '' }}</span>
                </a>
            @endforeach
        </div>
    @elseif ($q !== '' && ! $notFound)
        <div class="text-muted">{{ __('NoData') }}</div>
    @endif
</x-layouts.quick>
