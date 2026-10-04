@php
    $partsJson = $parts->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'partNumber' => $p->part_number, 'unit' => $p->unit, 'unitCost' => (float) $p->unit_cost, 'quantity' => $p->quantity])->values();
@endphp
@php $rows = old('items', [['quantity' => 1]]); @endphp
<x-layouts.app :title="__('NewPurchaseRequest')" dialog-size="lg">
    <h2>{{ __('NewPurchaseRequest') }}</h2>

    <form method="post" action="{{ route('purchases.store') }}">
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        <div class="mb-3">
            <p class="text-muted small mb-2">{{ __('PurchaseRequestHint') }}</p>
            <label class="form-label" for="reason">{{ __('Reason') }}</label>
            <textarea id="reason" name="reason" class="form-control" rows="2" placeholder="{{ __('ReasonPlaceholder') }}">{{ old('reason') }}</textarea>
        </div>

        <h5>{{ __('Items') }}</h5>
        <datalist id="partNames">
            @foreach ($parts as $p)
                <option value="{{ $p->name }}"></option>
            @endforeach
        </datalist>
        <div id="items">
            @foreach ($rows as $i => $row)
                <div class="card card-body mb-2 item-row">
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <label class="form-label small mb-0">{{ __('PartName') }}</label>
                            <input name="items[{{ $i }}][part_name]" value="{{ $row['part_name'] ?? '' }}" list="partNames" class="form-control part-name" autocomplete="off" placeholder="{{ __('PartNameHint') }}">
                            <input type="hidden" name="items[{{ $i }}][spare_part_id]" value="{{ $row['spare_part_id'] ?? '' }}" class="part-id">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-0">{{ __('PartNumber') }}</label>
                            <input name="items[{{ $i }}][part_number]" value="{{ $row['part_number'] ?? '' }}" class="form-control part-number">
                        </div>
                        <div class="col-3 col-md-1">
                            <label class="form-label small mb-0">{{ __('Quantity') }}</label>
                            <input name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" type="number" min="0" class="form-control part-qty">
                        </div>
                        <div class="col-3 col-md-1">
                            <label class="form-label small mb-0">{{ __('Unit') }}</label>
                            <input name="items[{{ $i }}][unit]" value="{{ $row['unit'] ?? '' }}" class="form-control part-unit">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-0">{{ __('EstimatedUnitPrice') }}</label>
                            <input name="items[{{ $i }}][estimated_unit_price]" value="{{ $row['estimated_unit_price'] ?? '' }}" type="number" step="0.01" min="0" inputmode="decimal" class="form-control part-price">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-0">{{ __('Notes') }}</label>
                            <input name="items[{{ $i }}][notes]" value="{{ $row['notes'] ?? '' }}" class="form-control">
                        </div>
                        <div class="col-12 small text-muted part-stock"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-outline-secondary mb-3" id="addItem">+ {{ __('AddItem') }}</button>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">{{ __('SubmitForApproval') }}</button>
            <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary btn-lg">{{ __('Cancel') }}</a>
        </div>
    </form>

    <x-slot:scripts>
        <script>
            (function () {
                const parts = {{ Js::from($partsJson) }};
                const stockLabel = @json(__('InStock'));
                const priceMissing = @json(__('PriceMissingWarning'));
                const container = document.getElementById('items');

                function bind(row) {
                    const name = row.querySelector('.part-name');
                    name.addEventListener('input', () => {
                        const p = parts.find(x => x.name === name.value.trim());
                        row.querySelector('.part-id').value = p ? p.id : '';
                        row.querySelector('.part-stock').textContent = p ? `${stockLabel}: ${p.quantity}` : '';
                        row.querySelector('.part-stock').classList.toggle('text-danger', !!p && !p.unitCost);
                        if (p && !p.unitCost) row.querySelector('.part-stock').textContent += ' — ' + priceMissing;
                        if (p) {
                            if (!row.querySelector('.part-number').value) row.querySelector('.part-number').value = p.partNumber || '';
                            if (!row.querySelector('.part-unit').value) row.querySelector('.part-unit').value = p.unit || '';
                            const price = row.querySelector('.part-price');
                            if (!parseFloat(price.value)) price.value = p.unitCost;
                        }
                    });
                }

                container.querySelectorAll('.item-row').forEach(bind);
                document.getElementById('addItem').addEventListener('click', () => {
                    const index = Date.now();
                    const row = container.querySelector('.item-row').cloneNode(true);
                    row.querySelectorAll('input').forEach(input => {
                        input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
                        input.value = input.classList.contains('part-qty') ? '1' : '';
                    });
                    row.querySelector('.part-stock').textContent = '';
                    container.appendChild(row);
                    bind(row);
                });
            })();
        </script>
    </x-slot:scripts>
</x-layouts.app>
