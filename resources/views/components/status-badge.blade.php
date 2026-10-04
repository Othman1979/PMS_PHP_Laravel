@props(['status', 'class' => ''])
<span {{ $attributes->merge(['class' => 'badge '.$status->badge().' '.$class]) }} @if (method_exists($status, 'badgeStyle')) style="{{ $status->badgeStyle() }}" @endif>{{ $status->label() }}</span>
