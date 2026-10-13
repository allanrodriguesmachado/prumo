@props(['value' => 0, 'color' => null])
@php
    $v = max(0, min(100, (float) $value));
    $c = $color ?? ($value >= 100 ? 'bg-rose-500' : ($value >= 80 ? 'bg-amber-500' : 'bg-brand-500'));
@endphp
<div {{ $attributes->merge(['class' => 'progress']) }}><div class="{{ $c }}" style="width: {{ $v }}%"></div></div>
