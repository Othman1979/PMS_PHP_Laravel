<x-layouts.app :title="__('StockMovements')">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ __('StockMovements') }}{{ $part ? ' — '.$part->name : '' }}</h2>
        <button class="btn btn-outline-secondary" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    @if ($part)
        <div class="alert alert-light border">
            {{ __('PartNumber') }}: {{ $part->part_number ?? '-' }} · {{ __('Manufacturer') }}: {{ $part->manufacturer ?? '-' }} ·
            <strong>{{ __('InStock') }}: {{ $part->quantity }} {{ $part->unit }}</strong> · {{ __('UnitCost') }}: {{ number_format($part->unit_cost, 2) }}
        </div>
    @endif

    <form method="get" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1" for="spare_part_id">{{ __('SparePart') }}</label>
                <select id="spare_part_id" name="spare_part_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($parts as $p)
                        <option value="{{ $p->id }}" @selected($partId === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1" for="type">{{ __('MovementType') }}</label>
                <select id="type" name="type" class="form-select">
                    <option value="">—</option>
                    @foreach (\App\Enums\StockMovementType::cases() as $t)
                        <option value="{{ $t->value }}" @selected($type === $t)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1" for="from">{{ __('From') }}</label>
                <input id="from" type="date" name="from" value="{{ $from?->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1" for="to">{{ __('To') }}</label>
                <input id="to" type="date" name="to" value="{{ $to?->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-6 col-md-auto"><button class="btn btn-primary w-100">{{ __('Filter') }}</button></div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card card-body text-center">
                <div class="small text-muted">{{ __('TotalReceivedValue') }}</div>
                <div class="fs-4 text-success">{{ number_format($totalReceived, 2) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-body text-center">
                <div class="small text-muted">{{ __('TotalIssuedValue') }}</div>
                <div class="fs-4 text-danger">{{ number_format($totalIssued, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('SparePart') }}</th><th>{{ __('MovementType') }}</th><th>{{ __('Quantity') }}</th>
                    <th>{{ __('UnitCost') }}</th><th>{{ __('BalanceAfter') }}</th><th>{{ __('Reference') }}</th><th>{{ __('By') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td class="text-nowrap">{{ $m->date->format('Y-m-d H:i') }}</td>
                        <td><a href="{{ route('parts.movements', ['spare_part_id' => $m->spare_part_id]) }}">{{ $m->sparePart?->name }}</a></td>
                        <td><x-status-badge :status="$m->type" /></td>
                        <td dir="ltr" class="text-end">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                        <td>{{ number_format($m->unit_cost, 2) }}</td>
                        <td>{{ $m->balance_after }}</td>
                        <td class="small">
                            @if ($m->goodsReceipt)
                                <a href="{{ route('receipts.show', $m->goodsReceipt) }}">{{ $m->goodsReceipt->number }}</a>
                                <span>({{ $m->goodsReceipt->purchaseRequest?->number }}) {{ $m->goodsReceipt->supplier }}</span>
                            @elseif ($m->maintenanceRequest)
                                <a href="{{ route('requests.show', $m->maintenanceRequest) }}">{{ $m->maintenanceRequest->request_number }}</a>
                                <span>{{ $m->maintenanceRequest->equipment?->name }} — {{ $m->maintenanceRequest->department?->localized_name }}</span>
                            @elseif ($m->note)
                                {{ __($m->note) }}
                            @endif
                        </td>
                        <td class="small">{{ $m->user?->full_name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
