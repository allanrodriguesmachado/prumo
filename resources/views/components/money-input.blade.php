@props(['name', 'value' => null, 'label' => null, 'help' => null, 'required' => false, 'id' => null])
@php $fid = $id ?? 'f-'.$name; $val = old($name, $value); $val = $val === null || $val === '' ? '' : parse_money($val); @endphp
<div x-data="money(@js($val))" {{ $attributes->only('class') }}>
    @if($label)<label class="label" for="{{ $fid }}">{{ $label }}</label>@endif
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-400">R$</span>
        <input id="{{ $fid }}" type="text" inputmode="numeric" name="{{ $name }}" x-model="raw" @input="format($event)" placeholder="0,00"
               @if($required) required @endif
               {{ $attributes->except('class')->merge(['class' => 'input pl-10 tabular font-semibold'.($errors->has($name) ? ' input-error' : '')]) }}>
    </div>
    @if($help)<p class="help">{{ $help }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
