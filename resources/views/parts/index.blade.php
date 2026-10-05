@php
    $user = auth()->user();
    $canManage = $user->canManage();
@endphp
<x-layouts.app :title="__('SpareParts')">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('SpareParts') }}</h2>
        @if ($canManage)
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="{{ route('purchases.create') }}">+ {{ __('NewPurchaseRequest') }}</a>
                <a class="btn btn-outline-secondary" href="{{ route('parts.movements') }}">{{ __('StockMovements') }}</a>
                <a class="btn btn-primary" href="{{ route('parts.create') }}">+ {{ __('AddSparePart') }}</a>
            </div>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr><th>{{ __('Name') }}</th><th>{{ __('PartNumber') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('MinimumQuantity') }}</th><th>{{ __('UnitCost') }}</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($parts as $p)
                    <tr class="{{ $p->isLow() ? 'table-warning' : '' }}">
                        <td>
                            {{ $p->name }} <span class="small text-muted">{{ $p->manufacturer }}</span>
                            @if ($p->is_food_grade)
                                <span class="badge bg-success-subtle text-success-emphasis border border-success" title="{{ __('FoodGradeHint') }}">{{ __('FoodGrade') }}</span>
                                @if ($p->food_grade_certificate_url)<a href="{{ $p->food_grade_certificate_url }}" target="_blank" class="small">📄</a>@endif
                            @endif
                        </td>
                        <td>{{ $p->part_number }}</td>
                        <td>
                            {{ $p->quantity }} {{ $p->unit }}
                            @if ($p->isLow())
                                <span class="badge bg-danger">{{ __('LowStock') }}</span>
                            @endif
                            @if ($canManage)
                                <form action="{{ route('parts.adjust', $p) }}" method="post" class="d-inline ms-2">
                                    @csrf
                                    <button name="delta" value="1" class="btn btn-sm btn-outline-secondary">+1</button>
                                    <button name="delta" value="-1" class="btn btn-sm btn-outline-secondary">-1</button>
                                </form>
                            @endif
                        </td>
                        <td>{{ $p->minimum_quantity }}</td>
                        <td>{{ number_format($p->unit_cost, 2) }}</td>
                        <td class="text-nowrap">
                            @if ($canManage)
                                <a class="btn btn-sm btn-outline-info" href="{{ route('parts.movements', ['spare_part_id' => $p->id]) }}">{{ __('PartHistory') }}</a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('parts.edit', $p) }}">{{ __('Edit') }}</a>
                            @endif
                            @if ($user->isAdmin())
                                <form action="{{ route('parts.destroy', $p) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('AreYouSure')))">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
