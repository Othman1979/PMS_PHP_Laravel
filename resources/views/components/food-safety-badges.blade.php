@props(['equipment', 'calibration' => true])
@php $cal = $equipment->calibrationStatus(); @endphp
@if ($equipment->food_contact)
    <span class="badge bg-info text-dark" title="{{ __('FoodContactHint') }}">{{ __('FoodContactShort') }}</span>
@endif
@if ($equipment->ccp_reference)
    <span class="badge bg-primary">{{ $equipment->ccp_reference }}</span>
@endif
@if ($equipment->is_critical)
    <span class="badge bg-danger">{{ __('CriticalShort') }}</span>
@endif
@if ($equipment->requiresCommissioning())
    <span class="badge bg-warning text-dark">{{ __('AwaitingCommissioning') }}</span>
@endif
@if ($calibration && $cal->needsAttention())
    <span class="badge {{ $cal->badge() }}">{{ $cal->label() }}</span>
@endif
