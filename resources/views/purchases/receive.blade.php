@php
    $rows = old('items', $pr->items->map(fn ($i) => [
        'spare_part_id' => $i->spare_part_id,
        'part_name' => $i->part_name,
        'part_number' => $i->part_number,
        'manufacturer' => $i->sparePart?->manufacturer,
        'unit' => $i->unit,
        'quantity' => $i->quantity,
        'unit_price' => $i->estimated_unit_price,
        'notes' => null,
    ])->all());
@endphp
<x-layouts.app :title="__('GoodsReceipt')" dialog-size="lg">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">{{ __('GoodsReceipt') }} — {{ $pr->number }}</h2>
        <a href="{{ route('purchases.show', $pr) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>

    <form method="post" action="{{ route('purchases.receive.store', $pr) }}" enctype="multipart/form-data">
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="supplier">{{ __('Supplier') }}</label>
                <input id="supplier" name="supplier" value="{{ old('supplier') }}" class="form-control" maxlength="200">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="invoice_number">{{ __('InvoiceNumber') }}</label>
                <input id="invoice_number" name="invoice_number" value="{{ old('invoice_number') }}" class="form-control" maxlength="100">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="invoice_date">{{ __('InvoiceDate') }}</label>
                <input id="invoice_date" name="invoice_date" type="date" value="{{ old('invoice_date', today()->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="attachment">{{ __('InvoiceAttachment') }}</label>
                <input id="attachment" type="file" name="attachment" class="form-control" accept="image/*,.pdf">
            </div>
        </div>

        <h5>{{ __('Items') }}</h5>
        @foreach ($rows as $i => $row)
            <div class="card card-body mb-2">
                <input type="hidden" name="items[{{ $i }}][spare_part_id]" value="{{ $row['spare_part_id'] ?? '' }}">
                <div class="row g-2">
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-0">{{ __('PartName') }}</label>
                        <input name="items[{{ $i }}][part_name]" value="{{ $row['part_name'] ?? '' }}" class="form-control" @readonly(filled($row['spare_part_id'] ?? null))>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">{{ __('PartNumber') }}</label>
                        <input name="items[{{ $i }}][part_number]" value="{{ $row['part_number'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">{{ __('Manufacturer') }}</label>
                        <input name="items[{{ $i }}][manufacturer]" value="{{ $row['manufacturer'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-4 col-md-1">
                        <label class="form-label small mb-0">{{ __('ReceivedQty') }}</label>
                        <input name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" type="number" min="0" class="form-control">
                    </div>
                    <div class="col-4 col-md-1">
                        <label class="form-label small mb-0">{{ __('Unit') }}</label>
                        <input name="items[{{ $i }}][unit]" value="{{ $row['unit'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-4 col-md-1">
                        <label class="form-label small mb-0">{{ __('UnitPrice') }}</label>
                        <input name="items[{{ $i }}][unit_price]" value="{{ $row['unit_price'] ?? '' }}" type="number" step="0.01" min="0" inputmode="decimal" class="form-control">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small mb-0">{{ __('Notes') }}</label>
                        <input name="items[{{ $i }}][notes]" value="{{ $row['notes'] ?? '' }}" class="form-control">
                    </div>
                </div>
            </div>
        @endforeach

        <div class="mb-3">
            <label class="form-label" for="notes">{{ __('Notes') }}</label>
            <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="btn btn-success btn-lg">{{ __('ConfirmReceipt') }}</button>
    </form>
</x-layouts.app>
