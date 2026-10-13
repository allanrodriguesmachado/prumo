@php
    $offset = $month->copy()->startOfMonth()->dayOfWeek; // 0 = domingo
    $days = $month->daysInMonth;
    $isCurrent = $month->isSameMonth(now());
    $today = now()->day;
    $weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $itemUrl = function (array $i) use ($month) {
        if (! empty($i['id'])) {
            return route('transactions.edit', $i['id']);
        }
        if (! empty($i['card'])) {
            return route('cards.invoice', ['card' => $i['card'], 'mes' => $month->format('Y-m')]);
        }

        return null;
    };
@endphp

<x-layouts.app title="Calendário">
    <x-page-header title="Calendário financeiro" subtitle="Contas, salários e faturas do mês, dia a dia — com o saldo previsto.">
        <x-slot:actions>
            <x-month-nav :month="$month" route="calendar.index" />
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 flex flex-wrap gap-2">
        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm text-emerald-800 ring-1 ring-emerald-200">
            ⬆️ Entradas <strong class="tabular">{{ brl($income) }}</strong>
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 text-sm text-rose-800 ring-1 ring-rose-200">
            ⬇️ Saídas <strong class="tabular">{{ brl($expense) }}</strong>
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-sm text-amber-800 ring-1 ring-amber-200">
            ⏳ A pagar <strong class="tabular">{{ brl($pending) }}</strong>
        </span>
    </div>

    {{-- Grade mensal (tablet/desktop) --}}
    <div class="card hidden overflow-hidden md:block">
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
            @foreach($weekdays as $i => $w)
                <div class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider {{ $i === 0 ? 'text-rose-500' : 'text-slate-500' }}">{{ $w }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7">
            @for($i = 0; $i < $offset; $i++)
                <div class="min-h-[120px] border-b border-r border-slate-100 bg-slate-50/50"></div>
            @endfor
            @for($d = 1; $d <= $days; $d++)
                @php
                    $items = $byDay[$d] ?? collect();
                    $holiday = $holidays[$d] ?? null;
                    $isToday = $isCurrent && $d === $today;
                    $col = ($offset + $d - 1) % 7;
                    $hasActivity = count($items) > 0;
                @endphp
                <div class="flex min-h-[120px] flex-col border-b border-slate-100 p-1.5 {{ $col < 6 ? 'border-r' : '' }} {{ $isToday ? 'bg-brand-50/70 ring-2 ring-inset ring-brand-400' : ($holiday ? 'bg-amber-50/40' : '') }}">
                    <div class="flex items-center justify-between">
                        <span class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold {{ $isToday ? 'bg-brand-600 text-white' : ($col === 0 || $holiday ? 'text-rose-500' : 'text-slate-600') }}">{{ $d }}</span>
                        @if($isToday)<span class="text-[10px] font-semibold uppercase text-brand-700">Hoje</span>@endif
                    </div>
                    @if($holiday)
                        <div class="mt-0.5 truncate text-[10.5px] font-medium text-amber-700" title="{{ $holiday }}">🎉 {{ $holiday }}</div>
                    @endif
                    <div class="mt-1 flex-1 space-y-0.5">
                        @foreach($items as $it)
                            @php
                                $url = $itemUrl($it);
                                $cls = $it['type'] === 'income' ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-rose-100 text-rose-800 hover:bg-rose-200';
                                $paidCls = $it['paid'] ? 'line-through opacity-50' : '';
                            @endphp
                            <{{ $url ? 'a' : 'div' }} @if($url) href="{{ $url }}" @endif
                                class="flex items-center gap-1 truncate rounded-md px-1.5 py-0.5 text-[10.5px] leading-tight {{ $cls }} {{ $paidCls }}"
                                title="{{ $it['label'] }} — {{ brl($it['amount']) }}{{ $it['paid'] ? ' (pago)' : '' }}">
                                <span class="shrink-0">{{ $it['icon'] }}</span>
                                <span class="min-w-0 flex-1 truncate">{{ $it['label'] }}</span>
                                <span class="shrink-0 font-semibold tabular">{{ num($it['amount'], 0) }}</span>
                            </{{ $url ? 'a' : 'div' }}>
                        @endforeach
                    </div>
                    @if($hasActivity || $d === 1 || $d === $days)
                        <div class="mt-1 text-right text-[10px] tabular {{ $running[$d] < 0 ? 'font-semibold text-rose-600' : 'text-slate-400' }}" title="Saldo acumulado do mês até este dia">
                            {{ brl($running[$d]) }}
                        </div>
                    @endif
                </div>
            @endfor
            @php $trailing = (7 - ($offset + $days) % 7) % 7; @endphp
            @for($i = 0; $i < $trailing; $i++)
                <div class="min-h-[120px] border-b border-slate-100 bg-slate-50/50 {{ $i < $trailing - 1 ? 'border-r' : '' }}"></div>
            @endfor
        </div>
        <div class="flex flex-wrap items-center gap-4 px-4 py-3 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-emerald-200"></span> Entrada</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-rose-200"></span> Saída</span>
            <span class="flex items-center gap-1.5"><span class="line-through opacity-60">riscado</span> = já pago</span>
            <span>O valor no rodapé de cada dia é o saldo acumulado do mês (entradas − saídas).</span>
        </div>
    </div>

    {{-- Agenda (celular) --}}
    <div class="space-y-3 md:hidden">
        @php $hasAny = false; @endphp
        @for($d = 1; $d <= $days; $d++)
            @php
                $items = $byDay[$d] ?? collect();
                $holiday = $holidays[$d] ?? null;
                $isToday = $isCurrent && $d === $today;
            @endphp
            @continue(count($items) === 0 && ! $holiday && ! $isToday)
            @php $hasAny = $hasAny || count($items) > 0; $date = $month->copy()->day($d); @endphp
            <div class="card overflow-hidden {{ $isToday ? 'ring-2 ring-brand-400' : '' }}">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2 {{ $isToday ? 'bg-brand-50' : 'bg-slate-50' }}">
                    <div class="flex items-center gap-2">
                        <span class="font-display text-lg font-bold text-slate-900">{{ str_pad($d, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="text-xs capitalize text-slate-500">{{ $date->translatedFormat('l') }}</span>
                        @if($isToday)<span class="badge-green">Hoje</span>@endif
                    </div>
                    <span class="text-xs tabular {{ $running[$d] < 0 ? 'font-semibold text-rose-600' : 'text-slate-400' }}">saldo {{ brl($running[$d]) }}</span>
                </div>
                @if($holiday)
                    <div class="px-4 pt-2 text-xs font-medium text-amber-700">🎉 Feriado: {{ $holiday }}</div>
                @endif
                <ul class="divide-y divide-slate-50">
                    @forelse($items as $it)
                        @php $url = $itemUrl($it); @endphp
                        <li>
                            <{{ $url ? 'a' : 'div' }} @if($url) href="{{ $url }}" @endif class="flex items-center gap-3 px-4 py-2.5 {{ $it['paid'] ? 'opacity-60' : '' }}">
                                <span class="text-lg">{{ $it['icon'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-800 {{ $it['paid'] ? 'line-through' : '' }}">{{ $it['label'] }}</span>
                                    <span class="text-[11px] {{ $it['paid'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $it['paid'] ? ($it['type'] === 'income' ? 'Recebido' : 'Pago') : ($it['type'] === 'income' ? 'A receber' : 'A pagar') }}</span>
                                </span>
                                <span class="text-sm font-semibold tabular {{ $it['type'] === 'income' ? 'money-pos' : 'money-neg' }}">{{ $it['type'] === 'income' ? '+' : '−' }}{{ brl($it['amount']) }}</span>
                            </{{ $url ? 'a' : 'div' }}>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-xs text-slate-400">Nada programado neste dia.</li>
                    @endforelse
                </ul>
            </div>
        @endfor
        @if(! $hasAny)
            <div class="card">
                <x-empty icon="📅" title="Nada programado neste mês" text="Cadastre salários, contas fixas e parcelas para vê-los no calendário.">
                    <a href="{{ route('recurrences.create') }}" class="btn-primary">+ Renda ou gasto fixo</a>
                </x-empty>
            </div>
        @endif
    </div>
</x-layouts.app>
