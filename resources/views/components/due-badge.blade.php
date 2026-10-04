@props(['request', 'class' => ''])
@php
    $due = $request->due_at;
    $open = $request->isOpen();
@endphp
@if ($due && $open)
    @if ($due->isPast())
        <span {{ $attributes->merge(['class' => 'badge bg-danger due-badge '.$class]) }} title="{{ __('DueAt') }}: {{ $due->format('Y-m-d H:i') }}">⏰ {{ str_replace('{0}', $due->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]), __('OverdueBy')) }}</span>
    @else
        <span {{ $attributes->merge(['class' => 'badge bg-light text-dark border due-badge '.$class]) }} title="{{ __('DueAt') }}: {{ $due->format('Y-m-d H:i') }}">{{ str_replace('{0}', now()->diffForHumans($due, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]), __('RemainingTime')) }}</span>
    @endif
@endif
