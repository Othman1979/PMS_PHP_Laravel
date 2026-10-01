@props(['status', 'class' => ''])
<span {{ $attributes->merge(['class' => 'badge '.$status->badge().' '.$class]) }}>{{ $status->label() }}</span>
