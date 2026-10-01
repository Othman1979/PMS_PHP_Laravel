@php
    $me = auth()->user();
    $isPending = $pr->status === \App\Enums\PurchaseRequestStatus::PendingApproval;
@endphp
<x-layouts.app :title="$pr->number">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">{{ __('PurchaseRequest') }} {{ $pr->number }} <x-status-badge :status="$pr->status" class="fs-6" /></h2>
        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('CreatedBy') }}</dt><dd class="col-sm-8">{{ $pr->createdBy?->full_name }}</dd>
                        <dt class="col-sm-4">{{ __('CreatedAt') }}</dt><dd class="col-sm-8">{{ $pr->created_at->format('Y-m-d H:i') }}</dd>
                        <dt class="col-sm-4">{{ __('Reason') }}</dt><dd class="col-sm-8">{{ $pr->reason }}</dd>
                        @if ($pr->decision_at)
                            <dt class="col-sm-4">{{ __('DecisionBy') }}</dt>
                            <dd class="col-sm-8">{{ $pr->decisionBy?->full_name }} — {{ $pr->decision_at->format('Y-m-d H:i') }}</dd>
                            @if (filled($pr->decision_note))
                                <dt class="col-sm-4">{{ __('DecisionNote') }}</dt><dd class="col-sm-8">{{ $pr->decision_note }}</dd>
                            @endif
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Items') }}</strong></div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead><tr><th>{{ __('PartName') }}</th><th>{{ __('PartNumber') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('EstimatedUnitPrice') }}</th><th>{{ __('TotalCost') }}</th><th>{{ __('Notes') }}</th></tr></thead>
                        <tbody>
                            @foreach ($pr->items as $i)
                                <tr>
                                    <td>{{ $i->part_name }}</td>
                                    <td>{{ $i->part_number }}</td>
                                    <td>{{ $i->quantity }} {{ $i->unit }}</td>
                                    <td>{{ number_format($i->estimated_unit_price, 2) }}</td>
                                    <td>{{ number_format($i->quantity * (float) $i->estimated_unit_price, 2) }}</td>
                                    <td class="small">{{ $i->notes }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th colspan="4">{{ __('EstimatedTotal') }}</th><th colspan="2">{{ number_format($pr->estimatedTotal(), 2) }}</th></tr></tfoot>
                    </table>
                </div>
            </div>

            @if ($pr->receipts->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('GoodsReceipts') }}</strong></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($pr->receipts as $r)
                            <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                                <a href="{{ route('receipts.show', $r) }}">{{ $r->number }}</a>
                                <span>{{ $r->supplier }} {{ $r->invoice_number ? '— '.$r->invoice_number : '' }}</span>
                                <span>{{ $r->received_at->format('Y-m-d') }} — {{ $r->receivedBy?->full_name }}</span>
                                <strong>{{ number_format($r->total(), 2) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            @if ($me->isAdmin() && $isPending)
                <div class="card mb-3 border-warning">
                    <div class="card-header"><strong>{{ __('ManagerDecision') }}</strong></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('purchases.approve', $pr) }}">
                            @csrf
                            <textarea name="note" class="form-control mb-2" rows="2" placeholder="{{ __('DecisionNote') }}"></textarea>
                            <div class="d-flex gap-2">
                                <button class="btn btn-success btn-lg flex-fill">{{ __('Approve') }}</button>
                                <button class="btn btn-outline-danger btn-lg flex-fill" formaction="{{ route('purchases.reject', $pr) }}">{{ __('Reject') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif ($isPending)
                <div class="alert alert-warning">{{ __('AwaitingManagerApproval') }}</div>
            @endif

            @if ($pr->status === \App\Enums\PurchaseRequestStatus::Approved)
                <a class="btn btn-primary btn-lg w-100 mb-3" href="{{ route('purchases.receive', $pr) }}">{{ __('EnterGoodsReceipt') }}</a>
            @endif

            @if ($isPending && ($pr->created_by_id === $me->id || $me->isAdmin()))
                <form method="post" action="{{ route('purchases.cancel', $pr) }}" onsubmit="return confirm(@js(__('AreYouSure')))">
                    @csrf
                    <button class="btn btn-outline-secondary w-100">{{ __('CancelRequest') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
