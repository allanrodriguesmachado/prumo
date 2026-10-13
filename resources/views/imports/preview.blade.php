@php
    $rows = $preview['rows'];
    $dupes = collect($rows)->where('duplicate', true)->count();
    $catsByType = $categories->groupBy('type');
@endphp

<x-layouts.app title="Revisar importação">
    <x-page-header title="Revise antes de importar" :subtitle="$preview['filename'].' · '.strtoupper($preview['format']).' · '.count($rows).' lançamento(s) encontrados'">
        <x-slot:actions>
            <a href="{{ route('imports.index') }}" class="btn-secondary">← Trocar arquivo</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('imports.store') }}"
          x-data="{
              count: 0, total: {{ count($rows) }},
              update() { this.count = this.$root.querySelectorAll('input.row-check:checked').length },
              all(v) { this.$root.querySelectorAll('input.row-check').forEach(c => c.checked = v); this.update() },
          }"
          x-init="update()" @change="update()">
        @csrf
        <input type="hidden" name="method" value="{{ $method }}">
        @if($isShared)<input type="hidden" name="is_shared" value="1">@endif

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="card card-pad">
                <div class="text-[13px] font-medium text-slate-500">Selecionados</div>
                <div class="mt-1 font-display text-2xl font-bold tabular text-brand-700"><span x-text="count">{{ count($rows) - $dupes }}</span> <span class="text-base font-medium text-slate-400">de {{ count($rows) }}</span></div>
            </div>
            <div class="card card-pad">
                <div class="text-[13px] font-medium text-slate-500">Já importados antes</div>
                <div class="mt-1 font-display text-2xl font-bold tabular {{ $dupes ? 'text-amber-600' : 'text-slate-900' }}">{{ $dupes }}</div>
                <div class="text-xs text-slate-500">Desmarcados para não duplicar.</div>
            </div>
            <div class="card card-pad">
                <div class="text-[13px] font-medium text-slate-500">Saldo do arquivo</div>
                @php $net = collect($rows)->sum('amount'); @endphp
                <div class="mt-1 font-display text-2xl font-bold tabular {{ $net >= 0 ? 'money-pos' : 'money-neg' }}">{{ brl($net) }}</div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" class="checkbox" :checked="count === total" :indeterminate="count > 0 && count < total" @change.stop="all($event.target.checked)">
                    Selecionar todos
                </label>
                <p class="text-xs text-slate-500">🧠 Categorias sugeridas pelo que o Prumo aprendeu. Ajuste se precisar — ele lembra na próxima.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr><th class="w-10"></th><th>Data</th><th>Descrição</th><th class="text-right">Valor</th><th class="min-w-[200px]">Categoria</th></tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $i => $r)
                        @php $type = $r['type']; $date = \Carbon\Carbon::parse($r['date']); @endphp
                        <tr class="{{ $r['duplicate'] ? 'bg-amber-50/50 text-slate-400' : '' }}">
                            <td>
                                <input type="checkbox" name="rows[{{ $i }}][selected]" value="1" class="checkbox row-check" @checked(! $r['duplicate'])>
                            </td>
                            <td class="whitespace-nowrap tabular">{{ $date->format('d/m/Y') }}</td>
                            <td>
                                <div class="max-w-[320px] truncate {{ $r['duplicate'] ? '' : 'text-slate-800' }}" title="{{ $r['description'] }}">{{ $r['description'] }}</div>
                                @if($r['duplicate'])<span class="badge-amber mt-1">já importado</span>@endif
                                @if(! empty($r['match_id']))<span class="badge-sky mt-1" title="Será conciliado com o lançamento existente em vez de duplicar">🔗 concilia com: {{ $r['match_label'] }}</span>@endif
                            </td>
                            <td class="whitespace-nowrap text-right font-semibold tabular {{ $type === 'income' ? 'money-pos' : 'money-neg' }}">
                                {{ $type === 'income' ? '+' : '−' }}{{ brl(abs($r['amount'])) }}
                            </td>
                            <td>
                                <select name="rows[{{ $i }}][category_id]" class="input py-1.5 text-sm">
                                    <option value="">Sem categoria</option>
                                    @foreach($catsByType[$type] ?? [] as $c)
                                        <option value="{{ $c->id }}" @selected((int) $r['category_id'] === $c->id)>{{ $c->icon }} {{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sticky bottom-0 mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-600">Serão importados <strong class="tabular" x-text="count"></strong> lançamento(s) como já pagos.</p>
            <div class="flex gap-2">
                <a href="{{ route('imports.index') }}" class="btn-ghost">Cancelar</a>
                <button class="btn-primary" :disabled="count === 0">Importar selecionados</button>
            </div>
        </div>
    </form>
</x-layouts.app>
