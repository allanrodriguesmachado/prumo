@props(['label', 'value', 'hint' => null, 'tone' => 'slate', 'icon' => null])
@php
    $tones = ['slate' => 'text-slate-900', 'green' => 'text-emerald-600', 'red' => 'text-rose-600', 'brand' => 'text-slate-900', 'amber' => 'text-amber-600', 'violet' => 'text-violet-600'];
@endphp
<div {{ $attributes->merge(['class' => 'card stat @container']) }}>
    <div class="flex items-start justify-between gap-2">
        <span class="text-xs font-medium text-slate-500 @[12rem]:text-[13px]">{{ $label }}</span>
        @if($icon)<span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-50 text-base ring-1 ring-slate-200/70" aria-hidden="true">{{ $icon }}</span>@endif
    </div>
    <div class="mt-1.5 whitespace-nowrap font-display text-lg font-semibold tracking-tight tabular @[11rem]:text-xl @[14rem]:text-2xl {{ $tones[$tone] ?? $tones['slate'] }}">{{ $value }}</div>
    @if($hint)<div class="mt-1 text-[11px] leading-snug text-slate-500 @[12rem]:text-xs">{!! $hint !!}</div>@endif
    {{ $slot }}
</div>
