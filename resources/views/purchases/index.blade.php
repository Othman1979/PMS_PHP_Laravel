<x-layouts.app :title="__('PurchaseRequests')">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('PurchaseRequests') }}</h2>
        <a class="btn btn-primary" href="{{ route('purchases.create') }}">+ {{ __('NewPurchaseRequest') }}</a>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">{{ __('Status') }}</option>
                @foreach (\App\Enums\PurchaseRequestStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr><th>{{ __('RequestNumber') }}</th><th>{{ __('Date') }}</th><th>{{ __('CreatedBy') }}</th><th>{{ __('Items') }}</th><th>{{ __('EstimatedTotal') }}</th><th>{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($purchases as $p)
                    <tr>
                        <td><a href="{{ route('purchases.show', $p) }}">{{ $p->number }}</a></td>
                        <td class="text-nowrap">{{ $p->created_at->format('Y-m-d') }}</td>
                        <td>{{ $p->createdBy?->full_name }}</td>
                        <td class="small">{{ $p->items->map(fn ($i) => $i->part_name.' × '.$i->quantity)->implode('، ') }}</td>
                        <td>{{ number_format($p->estimatedTotal(), 2) }}</td>
                        <td><x-status-badge :status="$p->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $purchases->links() }}
</x-layouts.app>
