@php $pr = $receipt->purchaseRequest; @endphp
<x-layouts.app :title="$receipt->number">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">{{ __('GoodsReceipt') }} {{ $receipt->number }}</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()">{{ __('Print') }}</button>
            <a href="{{ route('purchases.show', $pr) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('PurchaseRequest') }}</dt>
                <dd class="col-sm-9"><a href="{{ route('purchases.show', $pr) }}">{{ $pr->number }}</a> — {{ $pr->createdBy?->full_name }}</dd>
                <dt class="col-sm-3">{{ __('DecisionBy') }}</dt>
                <dd class="col-sm-9">{{ $pr->decisionBy?->full_name }} {{ $pr->decision_at?->format('Y-m-d') }}</dd>
                <dt class="col-sm-3">{{ __('Supplier') }}</dt><dd class="col-sm-9">{{ $receipt->supplier }}</dd>
                <dt class="col-sm-3">{{ __('InvoiceNumber') }}</dt>
                <dd class="col-sm-9">{{ $receipt->invoice_number }} {{ $receipt->invoice_date?->format('Y-m-d') }}
                    @if ($receipt->attachment_url)
                        <a href="{{ $receipt->attachment_url }}" target="_blank" class="ms-2">{{ __('View') }}</a>
                    @endif
                </dd>
                <dt class="col-sm-3">{{ __('ReceivedBy') }}</dt>
                <dd class="col-sm-9">{{ $receipt->receivedBy?->full_name }} — {{ $receipt->received_at->format('Y-m-d H:i') }}</dd>
                @if (filled($receipt->notes))
                    <dt class="col-sm-3">{{ __('Notes') }}</dt><dd class="col-sm-9">{{ $receipt->notes }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead><tr><th>{{ __('PartName') }}</th><th>{{ __('PartNumber') }}</th><th>{{ __('Manufacturer') }}</th><th>{{ __('ReceivedQty') }}</th><th>{{ __('UnitPrice') }}</th><th>{{ __('TotalCost') }}</th><th>{{ __('Notes') }}</th></tr></thead>
            <tbody>
                @foreach ($receipt->items as $i)
                    <tr>
                        <td><a href="{{ route('parts.movements', ['spare_part_id' => $i->spare_part_id]) }}">{{ $i->part_name }}</a></td>
                        <td>{{ $i->part_number }}</td>
                        <td>{{ $i->manufacturer }}</td>
                        <td>{{ $i->quantity }} {{ $i->unit }}</td>
                        <td>{{ number_format($i->unit_price, 2) }}</td>
                        <td>{{ number_format($i->quantity * (float) $i->unit_price, 2) }}</td>
                        <td class="small">{{ $i->notes }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><th colspan="5">{{ __('Total') }}</th><th colspan="2">{{ number_format($receipt->total(), 2) }}</th></tr></tfoot>
        </table>
    </div>
</x-layouts.app>
